<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\DeleteLatestManualCheckRunJob;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Support\Facades\Cache;

final class DeleteLatestManualCheckRunAction
{
    public const DELETING_CACHE_PREFIX = 'checks:deleting-manual-run:';

    public function execute(Site $site): int
    {
        $run = $this->find($site);

        if ($run === null) {
            return 0;
        }

        return $this->deleteByRunId((int) $site->id, (int) $run->id, $this->addressIdsForRun($run));
    }

    public function queue(Site $site): bool
    {
        $run = $this->find($site);

        if ($run === null || $this->isDeleting($site)) {
            return false;
        }

        $this->markDeleting($site);

        DeleteLatestManualCheckRunJob::dispatch(
            (int) $site->id,
            (int) $run->id,
            $this->addressIdsForRun($run),
        );

        return true;
    }

    public function find(Site $site): ?CheckRun
    {
        $run = $site->checkRuns()->orderByDesc('id')->first();

        if ($run === null || ! $this->isDeletableManualPass($run, $site->addresses()->count())) {
            return null;
        }

        return $run;
    }

    public function isDeleting(Site $site): bool
    {
        return Cache::has($this->deletingKey($site));
    }

    public function markDeleting(Site $site): void
    {
        Cache::put($this->deletingKey($site), true, $this->lockSeconds());
    }

    public function endDeleting(Site $site): void
    {
        Cache::forget($this->deletingKey($site));
    }

    /**
     * @param  list<int>  $addressIds
     */
    public function deleteByRunId(int $siteId, int $checkRunId, array $addressIds): int
    {
        $run = CheckRun::query()
            ->whereKey($checkRunId)
            ->where('site_id', $siteId)
            ->first();

        if ($run === null) {
            return 0;
        }

        $deleted = $this->deleteSnapshots($run);
        $this->restoreLastCheckedAt($addressIds);
        $run->delete();

        return $deleted;
    }

    /**
     * @return list<int>
     */
    public function addressIdsForRun(CheckRun $run): array
    {
        return $run->snapshots()
            ->distinct()
            ->pluck('address_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    private function isDeletableManualPass(CheckRun $run, int $siteAddressCount): bool
    {
        if ($run->source !== CheckRun::SOURCE_MANUAL || (int) $run->remaining_jobs !== 0) {
            return false;
        }

        $checkedAddressCount = (int) $run->snapshots()->distinct()->count('address_id');

        if ($checkedAddressCount < 1) {
            return false;
        }

        return $checkedAddressCount > 1 || $siteAddressCount <= 1;
    }

    private function deleteSnapshots(CheckRun $run): int
    {
        $deleted = 0;
        $chunkSize = max(1, (int) config('checking.snapshot_delete_chunk', 50));

        do {
            $ids = Snapshot::query()
                ->where('check_run_id', $run->id)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += (int) Snapshot::query()->whereIn('id', $ids)->delete();
        } while (true);

        return $deleted;
    }

    /**
     * @param  list<int>  $addressIds
     */
    private function restoreLastCheckedAt(array $addressIds): void
    {
        if ($addressIds === []) {
            return;
        }

        $latestIds = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->selectRaw('MAX(id) as id')
            ->groupBy('address_id')
            ->pluck('id');

        $latestByAddress = $latestIds->isEmpty()
            ? collect()
            : Snapshot::query()
                ->whereIn('id', $latestIds)
                ->get(['address_id', 'created_at'])
                ->mapWithKeys(fn (Snapshot $snapshot): array => [
                    (int) $snapshot->address_id => $snapshot->created_at,
                ]);

        foreach ($addressIds as $addressId) {
            Address::query()->whereKey($addressId)->update([
                'last_checked_at' => $latestByAddress->get($addressId),
            ]);
        }
    }

    private function deletingKey(Site $site): string
    {
        return self::DELETING_CACHE_PREFIX.$site->id;
    }

    private function lockSeconds(): int
    {
        return max(180, (int) config('checking.delete_run_lock_seconds', 7200));
    }
}
