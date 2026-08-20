<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckingGuard
{
    public const MANUAL_KEY_PREFIX = 'checks:manual:';

    public static function manualKey(int $siteId): string
    {
        return self::MANUAL_KEY_PREFIX.$siteId;
    }

    public function isBusy(?int $siteId = null): bool
    {
        if ($siteId !== null) {
            return Cache::has(self::manualKey($siteId)) || $this->hasPendingCheckJobs($siteId);
        }

        return $this->busySiteIds() !== [];
    }

    /**
     * @return list<int>
     */
    public function busySiteIds(): array
    {
        return $this->pendingJobSiteIds();
    }

    /**
     * @param  list<int>  $siteIds
     * @return list<int>
     */
    public function busySiteIdsAmong(array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        return array_values(array_intersect($this->busySiteIds(), $siteIds));
    }

    public function tryStartManual(int $siteId, int $ttlSeconds = 3600): bool
    {
        if ($this->hasPendingCheckJobs($siteId)) {
            return false;
        }

        return Cache::add(self::manualKey($siteId), true, $ttlSeconds);
    }

    public function endManual(int $siteId): void
    {
        Cache::forget(self::manualKey($siteId));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    public function runManual(int $siteId, callable $callback): mixed
    {
        if (! $this->tryStartManual($siteId)) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $this->endManual($siteId);
        }
    }

    public function isManualRunning(int $siteId): bool
    {
        return Cache::has(self::manualKey($siteId));
    }

    public function hasPendingCheckJobs(?int $siteId = null): bool
    {
        $ids = $this->pendingJobSiteIds();

        if ($siteId === null) {
            return $ids !== [];
        }

        return in_array($siteId, $ids, true);
    }

    /**
     * @return list<int>
     */
    private function pendingJobSiteIds(): array
    {
        if (config('queue.default') === 'sync') {
            return [];
        }

        if (! Schema::hasTable('jobs')) {
            return [];
        }

        $ids = [];

        foreach (DB::table('jobs')->pluck('payload') as $payload) {
            if (! is_string($payload) || ! str_contains($payload, 'CheckAddressJob')) {
                continue;
            }

            $siteId = $this->siteIdFromJobPayload($payload);
            if ($siteId !== null) {
                $ids[] = $siteId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function siteIdFromJobPayload(string $payload): ?int
    {
        $data = json_decode($payload, true);
        $command = is_array($data) ? ($data['data']['command'] ?? null) : null;

        if (! is_string($command)) {
            return null;
        }

        if (preg_match('/s:6:"siteId";i:(\d+);/', $command, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
