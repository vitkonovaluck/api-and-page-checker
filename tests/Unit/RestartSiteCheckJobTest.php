<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\CheckAddressJob;
use App\Jobs\RestartSiteCheckJob;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Services\CheckingGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RestartSiteCheckJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_queues_a_new_chain_run_when_enabled_and_idle(): void
    {
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => Site::SCHEDULE_INTERVAL_AFTER,
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);

        (new RestartSiteCheckJob($site->id))->handle(app(CheckingGuard::class));

        Queue::assertPushed(CheckAddressJob::class, 2);
        Queue::assertPushedOn(Site::checkQueueName($site->id), CheckAddressJob::class);
        $this->assertDatabaseCount('check_runs', 1);
        $this->assertSame(CheckRun::SOURCE_CHAIN, CheckRun::query()->first()?->source);
        Queue::assertNotPushed(RestartSiteCheckJob::class);
    }

    public function test_handle_skips_when_chain_interval_is_not_selected(): void
    {
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        (new RestartSiteCheckJob($site->id))->handle(app(CheckingGuard::class));

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('check_runs', 0);
    }

    public function test_handle_skips_when_site_is_busy(): void
    {
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => Site::SCHEDULE_INTERVAL_AFTER,
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        Cache::put(CheckingGuard::MANUAL_KEY, true, 60);

        (new RestartSiteCheckJob($site->id))->handle(app(CheckingGuard::class));

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('check_runs', 0);
    }

    public function test_job_is_assigned_to_the_site_queue(): void
    {
        $job = new RestartSiteCheckJob(7);

        $this->assertSame(Site::checkQueueName(7), $job->queue);
        $this->assertSame('chain-7', $job->uniqueId());
    }
}
