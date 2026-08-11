<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckingGuard
{
    public const MANUAL_KEY = 'checks:manual';

    public function isBusy(): bool
    {
        return Cache::has(self::MANUAL_KEY) || $this->hasPendingCheckJobs();
    }

    public function tryStartManual(int $ttlSeconds = 3600): bool
    {
        if ($this->hasPendingCheckJobs()) {
            return false;
        }

        return Cache::add(self::MANUAL_KEY, true, $ttlSeconds);
    }

    public function endManual(): void
    {
        Cache::forget(self::MANUAL_KEY);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    public function runManual(callable $callback): mixed
    {
        if (! $this->tryStartManual()) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $this->endManual();
        }
    }

    public function isManualRunning(): bool
    {
        return Cache::has(self::MANUAL_KEY);
    }

    public function hasPendingCheckJobs(): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        if (! Schema::hasTable('jobs')) {
            return false;
        }

        return DB::table('jobs')
            ->where('payload', 'like', '%CheckAddressJob%')
            ->exists();
    }
}
