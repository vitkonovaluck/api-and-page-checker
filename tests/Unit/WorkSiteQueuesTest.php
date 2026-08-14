<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\Commands\WorkSiteQueues;
use App\Jobs\CheckAddressJob;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkSiteQueuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_wanted_queue_names_include_each_site_and_default(): void
    {
        $first = Site::query()->create([
            'name' => 'First',
            'base_url' => 'https://first.example.com',
        ]);
        $second = Site::query()->create([
            'name' => 'Second',
            'base_url' => 'https://second.example.com',
        ]);

        $queues = app(WorkSiteQueues::class)->wantedQueueNames();

        $this->assertSame([
            CheckAddressJob::queueNameForSite($first->id),
            CheckAddressJob::queueNameForSite($second->id),
            'default',
        ], $queues);
    }
}
