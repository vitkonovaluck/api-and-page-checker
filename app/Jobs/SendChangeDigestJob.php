<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\DiffOptionsDTO;
use App\Enums\AlertEvent;
use App\Enums\DiffClassification;
use App\Mail\ChangeDigestMail;
use App\Models\AlertRule;
use App\Services\DiffService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendChangeDigestJob implements ShouldQueue
{
    use Queueable;

    public function handle(DiffService $diffService): void
    {
        $rules = AlertRule::query()
            ->where('digest_value_changes', true)
            ->with(['notificationChannel.user', 'site.addresses'])
            ->get();

        $byUser = [];

        foreach ($rules as $rule) {
            if ($rule->notificationChannel?->user === null || ! $rule->watches(AlertEvent::ValueChanged)) {
                continue;
            }

            foreach ($rule->site?->addresses ?? [] as $address) {
                if (! $rule->appliesTo($address)) {
                    continue;
                }

                $latest = $address->snapshots()->orderByDesc('id')->first();

                if ($latest === null || $latest->created_at?->lt(now()->subDay())) {
                    continue;
                }

                $previous = $latest->previous();
                $diff = $diffService->compare($previous, $latest, DiffOptionsDTO::fromAddress($address));

                if (($diff['classification'] ?? '') !== DiffClassification::ValueChange->value) {
                    continue;
                }

                $userId = $rule->notificationChannel->user_id;
                $byUser[$userId]['user'] = $rule->notificationChannel->user;
                $byUser[$userId]['email'] = $rule->notificationChannel->emailAddress();
                $byUser[$userId]['changes'][] = [
                    'site' => $rule->site?->name ?? '',
                    'endpoint' => $address->endpoint,
                    'snapshot_id' => $latest->id,
                ];
            }
        }

        foreach ($byUser as $payload) {
            if (($payload['email'] ?? null) === null || ($payload['changes'] ?? []) === []) {
                continue;
            }

            try {
                Mail::to($payload['email'])->send(new ChangeDigestMail(
                    $payload['user'],
                    collect($payload['changes']),
                ));
            } catch (Throwable $e) {
                Log::error('Change digest mail failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
