<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CheckRun;
use App\Models\Site;
use App\Services\CheckingGuard;

final class StopManualCheckRunAction
{
    public function __construct(
        private CheckingGuard $guard,
        private DeleteLatestManualCheckRunAction $deleteRun,
    ) {}

    public function execute(Site $site): int
    {
        $run = $this->prepareStop($site);

        if ($run === null) {
            return 0;
        }

        return $this->deleteRun->deleteByRunId(
            (int) $site->id,
            (int) $run->id,
            $this->siteAddressIds($site),
        );
    }

    public function queue(Site $site): bool
    {
        $run = $this->prepareStop($site);

        if ($run === null) {
            return false;
        }

        return $this->deleteRun->queueRun($site, $run, $this->siteAddressIds($site));
    }

    public function find(Site $site): ?CheckRun
    {
        return $site->checkRuns()
            ->where('source', CheckRun::SOURCE_MANUAL)
            ->where('remaining_jobs', '>', 0)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<int|string>  $siteIds
     * @return list<int>
     */
    public function stoppableSiteIds(array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        return CheckRun::query()
            ->whereIn('site_id', $siteIds)
            ->where('source', CheckRun::SOURCE_MANUAL)
            ->where('remaining_jobs', '>', 0)
            ->pluck('site_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function prepareStop(Site $site): ?CheckRun
    {
        $run = $this->find($site);

        if ($run === null || $this->deleteRun->isDeleting($site)) {
            return null;
        }

        $this->guard->cancelRun((int) $run->id);
        $this->guard->forgetPendingJobsForRun((int) $site->id, (int) $run->id);
        $run->forceFill(['remaining_jobs' => 0])->save();

        return $run;
    }

    /**
     * @return list<int>
     */
    private function siteAddressIds(Site $site): array
    {
        return $site->addresses()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
