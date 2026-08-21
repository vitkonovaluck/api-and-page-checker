<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;

final class DeleteLatestManualCheckRunAction
{
    public function execute(Site $site): int
    {
        $run = $this->find($site);

        if ($run === null) {
            return 0;
        }

        return (int) DB::transaction(fn (): int => $this->deleteRun($run));
    }

    public function find(Site $site): ?CheckRun
    {
        $run = $site->checkRuns()->orderByDesc('id')->first();

        if ($run === null || ! $this->isDeletableManualPass($run, $site->addresses()->count())) {
            return null;
        }

        return $run;
    }

    private function isDeletableManualPass(CheckRun $run, int $siteAddressCount): bool
    {
        if ($run->source !== CheckRun::SOURCE_MANUAL || (int) $run->remaining_jobs !== 0) {
            return false;
        }

        $checkedAddressCount = $run->snapshots()->pluck('address_id')->unique()->count();

        if ($checkedAddressCount < 1) {
            return false;
        }

        return $checkedAddressCount > 1 || $siteAddressCount <= 1;
    }

    private function deleteRun(CheckRun $run): int
    {
        $addressIds = $run->snapshots()
            ->pluck('address_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $deleted = (int) $run->snapshots()->delete();
        $this->restoreLastCheckedAt($addressIds);
        $run->delete();

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

        $latestByAddress = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->orderByDesc('id')
            ->get(['address_id', 'created_at'])
            ->unique('address_id')
            ->mapWithKeys(fn (Snapshot $snapshot): array => [
                (int) $snapshot->address_id => $snapshot->created_at,
            ]);

        foreach ($addressIds as $addressId) {
            Address::query()->whereKey($addressId)->update([
                'last_checked_at' => $latestByAddress->get($addressId),
            ]);
        }
    }
}
