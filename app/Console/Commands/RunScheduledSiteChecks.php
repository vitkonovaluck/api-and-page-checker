<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SnapshotChecker;
use Illuminate\Console\Command;

class RunScheduledSiteChecks extends Command
{
    protected $signature = 'sites:run-scheduled';

    protected $description = 'Run due scheduled site address checks';

    public function handle(SnapshotChecker $checker): int
    {
        $sites = Site::query()
            ->where('schedule_enabled', true)
            ->whereNotNull('schedule_interval')
            ->with(['addresses' => fn ($q) => $q->where('schedule_enabled', true)->orderBy('id')])
            ->get();

        $ran = 0;
        $checked = 0;

        foreach ($sites as $site) {
            if (! $site->isDueForScheduledCheck()) {
                continue;
            }

            $ran++;

            foreach ($site->addresses as $address) {
                $address->setRelation('site', $site);
                $checker->check($address);
                $checked++;
            }

            $site->forceFill(['schedule_last_run_at' => now()])->save();
            $this->info("Site #{$site->id} ({$site->name}): checked {$site->addresses->count()} address(es).");
        }

        if ($ran === 0) {
            $this->info('No sites due for scheduled check.');
        } else {
            $this->info("Done. Sites: {$ran}, addresses checked: {$checked}.");
        }

        return self::SUCCESS;
    }
}
