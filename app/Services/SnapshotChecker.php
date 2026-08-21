<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CheckRunCancelledException;
use App\Models\Address;
use App\Models\Snapshot;

class SnapshotChecker
{
    public function __construct(
        private HttpFetcher $fetcher,
        private DiffService $diffService,
        private CheckingGuard $guard,
    ) {}

    /**
     * @return array{snapshot: Snapshot, previous: ?Snapshot, diff: array}
     */
    public function check(Address $address, ?int $checkRunId = null): array
    {
        $this->assertRunAllowsSnapshots($checkRunId);

        $previous = $address->snapshots()->orderByDesc('id')->first();
        $address->loadMissing('site');
        $site = $address->site;
        $result = $this->fetcher->request(
            $address->http_method ?? 'GET',
            $address->fullUrl(),
            $address->request_headers ?? [],
            $address->supportsRequestBody() ? $address->request_body : null,
            $site->checksPerMinute(),
            'site-'.$site->id,
        );

        $this->assertRunAllowsSnapshots($checkRunId);

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
        ]);

        $address->forceFill(['last_checked_at' => now()])->save();

        $diff = $this->diffService->compare($previous, $snapshot);

        return [
            'snapshot' => $snapshot,
            'previous' => $previous,
            'diff' => $diff,
        ];
    }

    private function assertRunAllowsSnapshots(?int $checkRunId): void
    {
        if ($checkRunId === null || ! $this->guard->isRunCancelled($checkRunId)) {
            return;
        }

        throw new CheckRunCancelledException($checkRunId);
    }
}
