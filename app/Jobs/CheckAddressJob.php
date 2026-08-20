<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Services\SnapshotChecker;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Collection;
use Throwable;

class CheckAddressJob implements ShouldQueue
{
    use Queueable;

    /**
     * Rate-limit releases count as attempts. Keep this high enough that the
     * tail of a site run can wait for rpm slots instead of dying at tries=3.
     */
    public int $tries = 25;

    public int $maxExceptions = 3;

    public int $timeout = 90;

    public int $siteId;

    public function __construct(
        public Address $address,
        public ?int $checkRunId = null,
    ) {
        $this->siteId = (int) $address->site_id;
        $this->onQueue(Site::checkQueueName($this->siteId));
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * @param  iterable<int, Address>  $addresses
     */
    public static function dispatchForSite(Site $site, string $source, iterable $addresses): CheckRun
    {
        $addressList = Collection::make($addresses);
        $run = CheckRun::start($site, $source, $addressList->count());

        foreach ($addressList as $address) {
            self::dispatch($address, $run->id);
        }

        return $run;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('address-checks')];
    }

    public function handle(SnapshotChecker $checker): void
    {
        $this->address->loadMissing('site');
        $checker->check($this->address, $this->checkRunId);

        $delay = (int) config('checking.delay_seconds', 1);
        if ($delay > 0) {
            sleep($delay);
        }

        $this->maybeQueueChainedCheck();
    }

    public function failed(?Throwable $exception): void
    {
        $this->maybeQueueChainedCheck();
    }

    private function maybeQueueChainedCheck(): void
    {
        if ($this->checkRunId === null || ! RestartSiteCheckJob::releaseSlot($this->checkRunId)) {
            return;
        }

        $site = Site::query()->find($this->siteId);
        if ($site === null || ! $site->usesChainChecks()) {
            return;
        }

        RestartSiteCheckJob::dispatchDelayed($this->siteId);
    }
}
