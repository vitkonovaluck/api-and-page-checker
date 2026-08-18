<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Address;
use App\Services\SnapshotChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class CheckAddressJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $siteId;

    public function __construct(
        public Address $address,
        public ?int $checkRunId = null,
    ) {
        $this->siteId = (int) $address->site_id;
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
    }
}
