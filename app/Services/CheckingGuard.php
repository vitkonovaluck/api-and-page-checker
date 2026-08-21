<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CheckRun;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckingGuard
{
    public const MANUAL_KEY_PREFIX = 'checks:manual:';

    public const CANCELLED_RUN_PREFIX = 'checks:cancelled-run:';

    public static function manualKey(int $siteId): string
    {
        return self::MANUAL_KEY_PREFIX.$siteId;
    }

    public static function cancelledRunKey(int $checkRunId): string
    {
        return self::CANCELLED_RUN_PREFIX.$checkRunId;
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

    public function cancelRun(int $checkRunId): void
    {
        Cache::put(
            self::cancelledRunKey($checkRunId),
            true,
            max(180, (int) config('checking.delete_run_lock_seconds', 7200)),
        );
    }

    public function isRunCancelled(int $checkRunId): bool
    {
        if (Cache::has(self::cancelledRunKey($checkRunId))) {
            return true;
        }

        return ! CheckRun::query()->whereKey($checkRunId)->exists();
    }

    public function forgetPendingJobsForRun(int $siteId, int $checkRunId): int
    {
        if (! $this->canInspectJobs()) {
            return 0;
        }

        $deleted = 0;

        foreach ($this->checkJobRows($siteId) as $job) {
            if (! $this->jobMatchesCheckRun($job->payload, $checkRunId)) {
                continue;
            }

            $deleted += (int) DB::table('jobs')->where('id', $job->id)->delete();
        }

        return $deleted;
    }

    /**
     * @return list<int>
     */
    private function pendingJobSiteIds(): array
    {
        if (! $this->canInspectJobs()) {
            return [];
        }

        $ids = [];

        foreach (DB::table('jobs')->pluck('payload') as $payload) {
            if (! is_string($payload) || ! str_contains($payload, 'CheckAddressJob')) {
                continue;
            }

            $siteId = $this->intPropertyFromJobPayload($payload, 'siteId');
            if ($siteId !== null) {
                $ids[] = $siteId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return Collection<int, object>
     */
    private function checkJobRows(int $siteId): iterable
    {
        return DB::table('jobs')
            ->where('queue', Site::checkQueueName($siteId))
            ->get(['id', 'payload']);
    }

    private function jobMatchesCheckRun(mixed $payload, int $checkRunId): bool
    {
        if (! is_string($payload) || ! str_contains($payload, 'CheckAddressJob')) {
            return false;
        }

        return $this->intPropertyFromJobPayload($payload, 'checkRunId') === $checkRunId;
    }

    private function canInspectJobs(): bool
    {
        return config('queue.default') !== 'sync' && Schema::hasTable('jobs');
    }

    private function intPropertyFromJobPayload(string $payload, string $property): ?int
    {
        $command = $this->commandFromJobPayload($payload);

        if ($command === null) {
            return null;
        }

        $pattern = '/s:'.strlen($property).':"'.preg_quote($property, '/').'";i:(\d+);/';

        if (preg_match($pattern, $command, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function commandFromJobPayload(string $payload): ?string
    {
        $data = json_decode($payload, true);
        $command = is_array($data) ? ($data['data']['command'] ?? null) : null;

        return is_string($command) ? $command : null;
    }
}
