<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AlertEvent;
use App\Enums\CheckOutcome;
use App\Enums\DiffClassification;
use App\Jobs\SendAlertNotificationJob;
use App\Models\Address;
use App\Models\AlertRule;
use App\Models\CheckRun;
use App\Models\Snapshot;

final class EvaluateAlertRulesAction
{
    /**
     * @param  array<string, mixed>  $diff
     * @return list<AlertEvent>
     */
    public function execute(Address $address, Snapshot $snapshot, array $diff, string $source): array
    {
        $events = $this->firedEvents($snapshot, $diff);

        if ($events === [] || ($diff['is_first'] ?? false)) {
            return [];
        }

        $rules = AlertRule::query()
            ->where('site_id', $address->site_id)
            ->with('notificationChannel')
            ->get();

        $dispatched = [];

        foreach ($rules as $rule) {
            if (! $this->shouldDispatch($rule, $address, $snapshot, $events, $source, $diff)) {
                continue;
            }

            SendAlertNotificationJob::dispatch($rule->id, $snapshot->id, array_map(
                fn (AlertEvent $event): string => $event->value,
                $events,
            ));

            $rule->forceFill(['last_sent_at' => now()])->save();
            $dispatched = $events;
        }

        return $dispatched;
    }

    /**
     * @param  array<string, mixed>  $diff
     * @return list<AlertEvent>
     */
    private function firedEvents(Snapshot $snapshot, array $diff): array
    {
        $events = [];

        if ($snapshot->check_outcome === CheckOutcome::Failed || $snapshot->error_message) {
            $events[] = AlertEvent::CheckFailed;
        }

        if ($snapshot->check_outcome === CheckOutcome::Degraded) {
            $events[] = AlertEvent::SlowResponse;
        }

        if ($diff['status_code']['changed'] ?? false) {
            $events[] = AlertEvent::StatusChanged;
        }

        if (($diff['headers'] ?? []) !== []) {
            $events[] = AlertEvent::HeadersChanged;
        }

        if ($diff['body']['changed'] ?? false) {
            $events[] = AlertEvent::BodyChanged;
            $classification = DiffClassification::tryFrom((string) ($diff['classification'] ?? ''));

            if ($classification === DiffClassification::SchemaChange || $classification === DiffClassification::Mixed) {
                $events[] = AlertEvent::SchemaChanged;
            }

            if ($classification === DiffClassification::ValueChange || $classification === DiffClassification::Mixed) {
                $events[] = AlertEvent::ValueChanged;
            }
        }

        return $events;
    }

    /**
     * @param  list<AlertEvent>  $events
     * @param  array<string, mixed>  $diff
     */
    private function shouldDispatch(
        AlertRule $rule,
        Address $address,
        Snapshot $snapshot,
        array $events,
        string $source,
        array $diff,
    ): bool {
        if (! $rule->appliesTo($address)) {
            return false;
        }

        if ($rule->notificationChannel === null || ! $rule->notificationChannel->is_enabled) {
            return false;
        }

        if ($source === CheckRun::SOURCE_MANUAL && ! $rule->notify_on_manual) {
            return false;
        }

        if ($rule->isCoolingDown()) {
            return false;
        }

        $matched = array_values(array_filter(
            $events,
            fn (AlertEvent $event): bool => $rule->watches($event),
        ));

        if ($matched === []) {
            return false;
        }

        if ($this->isDigestOnlyValueChange($rule, $matched, $diff)) {
            return false;
        }

        return $this->meetsConsecutive($rule, $address, $snapshot);
    }

    /**
     * @param  list<AlertEvent>  $matched
     * @param  array<string, mixed>  $diff
     */
    private function isDigestOnlyValueChange(AlertRule $rule, array $matched, array $diff): bool
    {
        if (! $rule->digest_value_changes) {
            return false;
        }

        $onlyValue = $matched === [AlertEvent::ValueChanged]
            || $matched === [AlertEvent::BodyChanged, AlertEvent::ValueChanged]
            || $matched === [AlertEvent::ValueChanged, AlertEvent::BodyChanged];

        return $onlyValue && ($diff['classification'] ?? '') === DiffClassification::ValueChange->value;
    }

    private function meetsConsecutive(AlertRule $rule, Address $address, Snapshot $snapshot): bool
    {
        $needed = max(1, (int) $rule->min_consecutive);

        if ($needed === 1) {
            return true;
        }

        $recent = $address->snapshots()
            ->where('id', '<=', $snapshot->id)
            ->orderByDesc('id')
            ->limit($needed)
            ->get();

        if ($recent->count() < $needed) {
            return false;
        }

        return $recent->every(
            fn (Snapshot $item): bool => $item->check_outcome !== CheckOutcome::Ok,
        );
    }
}
