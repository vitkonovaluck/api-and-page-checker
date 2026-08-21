<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DeleteLatestManualCheckRunAction;
use App\Models\Site;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DeleteLatestManualCheckRunJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 60;

    public int $uniqueFor = 7200;

    /**
     * @param  list<int>  $addressIds
     */
    public function __construct(
        public int $siteId,
        public int $checkRunId,
        public array $addressIds,
    ) {
        $this->onQueue(Site::checkQueueName($siteId));
        $this->uniqueFor = max(180, (int) config('checking.delete_run_lock_seconds', 7200));
    }

    public function uniqueId(): string
    {
        return 'delete-manual-run-'.$this->siteId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addSeconds(max(180, (int) config('checking.delete_run_lock_seconds', 7200)));
    }

    public function handle(DeleteLatestManualCheckRunAction $action): void
    {
        $action->deleteByRunId($this->siteId, $this->checkRunId, $this->addressIds);
        $this->clearDeletingFlag($action);
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(DeleteLatestManualCheckRunAction::DELETING_CACHE_PREFIX.$this->siteId);

        Log::error('Failed to delete latest manual check run', [
            'site_id' => $this->siteId,
            'check_run_id' => $this->checkRunId,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function clearDeletingFlag(DeleteLatestManualCheckRunAction $action): void
    {
        $site = Site::query()->find($this->siteId);

        if ($site !== null) {
            $action->endDeleting($site);
        }
    }
}
