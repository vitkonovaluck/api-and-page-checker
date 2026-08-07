<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Snapshot;

class SnapshotChecker
{
    public function __construct(
        private HttpFetcher $fetcher,
        private DiffService $diffService,
    ) {}

    /**
     * @return array{snapshot: Snapshot, previous: ?Snapshot, diff: array}
     */
    public function check(Address $address): array
    {
        $previous = $address->snapshots()->orderByDesc('id')->first();
        $address->loadMissing('site');
        $result = $this->fetcher->get($address->fullUrl(), $address->request_headers ?? []);

        $snapshot = Snapshot::query()->create([
            'address_id' => $address->id,
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
}
