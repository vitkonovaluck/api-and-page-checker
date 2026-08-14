<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Address;
use App\Models\Site;
use App\Services\SnapshotChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class CheckAddressJob implements ShouldQueue
{
    use Queueable;

    public const QUEUE_PREFIX = 'site-';

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public Address $address,
        public ?int $checkRunId = null,
    ) {
        $this->onQueue(self::queueNameForSite((int) $address->site_id));
    }

    public static function queueNameForSite(int $siteId): string
    {
        return self::QUEUE_PREFIX.$siteId;
    }

    public static function siteIdFromQueueName(string $queue): ?int
    {
        if (! str_starts_with($queue, self::QUEUE_PREFIX)) {
            return null;
        }

        $id = substr($queue, strlen(self::QUEUE_PREFIX));

        if ($id === '' || ! ctype_digit($id)) {
            return null;
        }

        return (int) $id;
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
    }

    /**
     * @param  iterable<int, Address>  $addresses
     */
    public static function dispatchForSite(Site $site, iterable $addresses, int $checkRunId): int
    {
        $slotMs = (int) config('checking.delay_seconds', 1) === 0
            ? 0
            : $site->checkIntervalMilliseconds();

        $offsetMs = 0;
        $count = 0;

        foreach ($addresses as $address) {
            $pending = self::dispatch($address, $checkRunId);

            if ($offsetMs > 0) {
                $pending->delay(now()->addMilliseconds($offsetMs));
            }

            $offsetMs += $slotMs;
            $count++;
        }

        return $count;
    }
}
