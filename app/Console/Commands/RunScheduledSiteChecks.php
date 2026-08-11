<?php

namespace App\Console\Commands;

use App\Jobs\CheckAddressJob;
use App\Models\CheckRun;
use App\Models\Site;
use App\Services\CheckingGuard;
use Illuminate\Console\Command;

class RunScheduledSiteChecks extends Command
{
    protected $signature = 'sites:run-scheduled';

    protected $description = 'Enqueue due scheduled site address checks';

    public function handle(CheckingGuard $guard): int
    {
        if ($guard->isManualRunning()) {
            $this->warn('Skipping scheduled enqueue: a manual check is in progress.');

            return self::SUCCESS;
        }

        $sites = Site::query()
            ->where('schedule_enabled', true)
            ->whereNotNull('schedule_interval')
            ->with(['addresses' => fn ($q) => $q->where('schedule_enabled', true)->orderBy('id')])
            ->get();

        $ran = 0;
        $queued = 0;

        foreach ($sites as $site) {
            // Claim first so a second concurrent scheduler process skips this site.
            if (! $site->claimForScheduledCheck()) {
                continue;
            }

            $ran++;

            $run = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);

            foreach ($site->addresses as $address) {
                CheckAddressJob::dispatch($address, $run->id);
                $queued++;
            }

            $this->info("Site #{$site->id} ({$site->name}): queued {$site->addresses->count()} address(es).");
        }

        if ($ran === 0) {
            $this->info('No sites due for scheduled check.');
        } else {
            $this->info("Done. Sites: {$ran}, addresses queued: {$queued}.");
        }

        return self::SUCCESS;
    }
}
