<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CheckAddressJob;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckingGuard
{
    public const MANUAL_KEY_PREFIX = 'checks:manual:';

    public static function manualCacheKey(int $siteId): string
    {
        return self::MANUAL_KEY_PREFIX.$siteId;
    }

    public function isBusy(int $siteId): bool
    {
        return Cache::has(self::manualCacheKey($siteId)) || $this->hasPendingCheckJobs($siteId);
    }

    /**
     * @return list<int>
     */
    public function busySiteIds(): array
    {
        $ids = [];

        foreach ($this->pendingJobSiteIds() as $siteId) {
            $ids[$siteId] = $siteId;
        }

        foreach (Site::query()->pluck('id') as $siteId) {
            $siteId = (int) $siteId;
            if (Cache::has(self::manualCacheKey($siteId))) {
                $ids[$siteId] = $siteId;
            }
        }

        return array_values($ids);
    }

    public function tryStartManual(int $siteId, int $ttlSeconds = 3600): bool
    {
        if ($this->hasPendingCheckJobs($siteId)) {
            return false;
        }

        return Cache::add(self::manualCacheKey($siteId), true, $ttlSeconds);
    }

    public function endManual(int $siteId): void
    {
        Cache::forget(self::manualCacheKey($siteId));
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
        return Cache::has(self::manualCacheKey($siteId));
    }

    public function hasPendingCheckJobs(int $siteId): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        if (! Schema::hasTable('jobs')) {
            return false;
        }

        return DB::table('jobs')
            ->where('queue', CheckAddressJob::queueNameForSite($siteId))
            ->exists();
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

        foreach (DB::table('jobs')->distinct()->pluck('queue') as $queue) {
            $siteId = CheckAddressJob::siteIdFromQueueName((string) $queue);
            if ($siteId !== null) {
                $ids[] = $siteId;
            }
        }

        return $ids;
    }
}
