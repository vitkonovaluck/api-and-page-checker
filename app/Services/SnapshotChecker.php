<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\EvaluateAssertionsAction;
use App\Actions\SubstituteRunVariablesAction;
use App\DTOs\DiffOptionsDTO;
use App\Enums\CheckOutcome;
use App\Events\SnapshotRecorded;
use App\Exceptions\CheckRunCancelledException;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Snapshot;

class SnapshotChecker
{
    public function __construct(
        private HttpFetcher $fetcher,
        private DiffService $diffService,
        private CheckingGuard $guard,
        private EvaluateAssertionsAction $assertions,
        private SubstituteRunVariablesAction $substitutor,
    ) {}

    /**
     * @return array{snapshot: Snapshot, previous: ?Snapshot, diff: array<string, mixed>, alert_diff: array<string, mixed>}
     */
    public function check(Address $address, ?int $checkRunId = null): array
    {
        $this->assertRunAllowsSnapshots($checkRunId);

        $previous = $address->snapshots()->orderByDesc('id')->first();
        $address->loadMissing(['site', 'siteToken', 'baselineSnapshot']);
        $run = $checkRunId !== null ? CheckRun::query()->find($checkRunId) : null;
        $variables = $run?->variables ?? [];
        $site = $address->site;
        $result = $this->fetcher->request(
            $address->http_method ?? 'GET',
            $this->substitutor->execute($address->fullUrl(), $variables),
            $this->substitutor->headers($address->resolvedRequestHeaders(), $variables),
            $address->supportsRequestBody()
                ? $this->substitutor->execute((string) $address->request_body, $variables)
                : null,
            $site->checksPerMinute(),
            'site-'.$site->id,
        );

        $this->assertRunAllowsSnapshots($checkRunId);

        $assertion = $this->assertions->execute($address, $result);
        $options = DiffOptionsDTO::fromAddress($address);

        $snapshot = Snapshot::query()->create([
            'address_id' => $address->id,
            'check_run_id' => $checkRunId,
            'status_code' => $result->statusCode,
            'headers' => $result->headers,
            'body' => $result->body,
            'body_hash' => hash('sha256', $result->body),
            'response_time_ms' => $result->responseTimeMs,
            'timing' => $result->timing,
            'error_message' => $result->errorMessage,
            'assertion_failed' => $assertion['failed'] || $assertion['degraded'],
            'assertion_results' => $assertion['results'],
        ]);

        $historyDiff = $this->diffService->compare($previous, $snapshot, $options);
        $compareTo = $address->baselineSnapshot ?? $previous;
        $alertDiff = $compareTo !== null && $previous !== null && $compareTo->id !== $previous->id
            ? $this->diffService->compare($compareTo, $snapshot, $options)
            : $historyDiff;

        $outcome = $this->outcome($result->errorMessage, $assertion, $alertDiff);
        $snapshot->forceFill(['check_outcome' => $outcome])->save();
        $address->forceFill(['last_checked_at' => now()])->save();

        $this->extractVariable($address, $snapshot, $run);

        SnapshotRecorded::dispatch(
            $snapshot,
            $alertDiff,
            $run?->source ?? CheckRun::SOURCE_MANUAL,
        );

        return [
            'snapshot' => $snapshot,
            'previous' => $previous,
            'diff' => $historyDiff,
            'alert_diff' => $alertDiff,
        ];
    }

    /**
     * @param  array{failed: bool, degraded: bool, results: list<array<string, mixed>>}  $assertion
     * @param  array<string, mixed>  $diff
     */
    private function outcome(?string $errorMessage, array $assertion, array $diff): CheckOutcome
    {
        if ($errorMessage !== null || $assertion['failed']) {
            return CheckOutcome::Failed;
        }

        if ($assertion['degraded']) {
            return CheckOutcome::Degraded;
        }

        if (($diff['has_changes'] ?? false) && ! ($diff['is_first'] ?? false)) {
            return CheckOutcome::Changed;
        }

        return CheckOutcome::Ok;
    }

    private function extractVariable(Address $address, Snapshot $snapshot, ?CheckRun $run): void
    {
        if ($run === null || $address->extract_json_path === null || $address->extract_as === null) {
            return;
        }

        $decoded = json_decode((string) $snapshot->body, true);

        if (! is_array($decoded)) {
            return;
        }

        $value = JsonPathFilter::get($decoded, $address->extract_json_path);

        if (! is_scalar($value) && $value !== null) {
            return;
        }

        $variables = $run->variables ?? [];
        $variables[$address->extract_as] = $value;
        $run->forceFill(['variables' => $variables])->save();
    }

    private function assertRunAllowsSnapshots(?int $checkRunId): void
    {
        if ($checkRunId === null || ! $this->guard->isRunCancelled($checkRunId)) {
            return;
        }

        throw new CheckRunCancelledException($checkRunId);
    }
}
