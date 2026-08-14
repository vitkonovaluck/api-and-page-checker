<?php

declare(strict_types=1);

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
        $sites = Site::query()
            ->where('schedule_enabled', true)
            ->whereNotNull('schedule_interval')
            ->with(['addresses' => fn ($q) => $q->where('schedule_enabled', true)->orderBy('id')])
            ->get();

        $ran = 0;
        $queued = 0;
        $skipped = 0;

        foreach ($sites as $site) {
            if ($guard->isBusy($site->id)) {
                $skipped++;
                $this->warn("Skipping site #{$site->id} ({$site->name}): a check is already in progress.");

                continue;
            }

            // Claim first so a second concurrent scheduler process skips this site.
            if (! $site->claimForScheduledCheck()) {
                continue;
            }

            $ran++;

            $run = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);

            $queued += CheckAddressJob::dispatchForSite($site, $site->addresses, $run->id);

            $this->info("Site #{$site->id} ({$site->name}): queued {$site->addresses->count()} address(es) on ".CheckAddressJob::queueNameForSite($site->id).'.');
        }

        if ($ran === 0 && $skipped === 0) {
            $this->info('No sites due for scheduled check.');
        } else {
            $this->info("Done. Sites: {$ran}, skipped busy: {$skipped}, addresses queued: {$queued}.");
        }

        return self::SUCCESS;
    }
}
