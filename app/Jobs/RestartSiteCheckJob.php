<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CheckRun;
use App\Models\Site;
use App\Services\CheckingGuard;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RestartSiteCheckJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 180;

    public function __construct(public int $siteId)
    {
        $this->onQueue(Site::checkQueueName($siteId));
        $this->uniqueFor = max(180, self::delaySeconds() + 120);
    }

    public function uniqueId(): string
    {
        return 'chain-'.$this->siteId;
    }

    public static function releaseSlot(int $checkRunId): bool
    {
        $updated = CheckRun::query()
            ->whereKey($checkRunId)
            ->where('remaining_jobs', '>', 0)
            ->decrement('remaining_jobs');

        if ($updated === 0) {
            return false;
        }

        return CheckRun::query()
            ->whereKey($checkRunId)
            ->where('remaining_jobs', '<=', 0)
            ->exists();
    }

    public static function dispatchDelayed(int $siteId): void
    {
        self::dispatch($siteId)->delay(now()->addSeconds(self::delaySeconds()));
    }

    public function handle(CheckingGuard $guard): void
    {
        $site = Site::query()->find($this->siteId);

        if ($site === null || ! $site->usesChainChecks()) {
            return;
        }

        if ($guard->isBusy($this->siteId)) {
            $this->retryLater();

            return;
        }

        $addresses = $site->addresses()
            ->where('schedule_enabled', true)
            ->orderBy('id')
            ->get();
        if ($addresses->isEmpty()) {
            return;
        }

        CheckAddressJob::dispatchForSite($site, CheckRun::SOURCE_CHAIN, $addresses);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to restart site check', [
            'site_id' => $this->siteId,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function retryLater(): void
    {
        if ($this->job === null) {
            return;
        }

        $this->release(self::delaySeconds());
    }

    private static function delaySeconds(): int
    {
        return max(1, (int) config('checking.chain_delay_seconds', 60));
    }
}
