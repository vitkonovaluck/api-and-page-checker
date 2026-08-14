<?php

namespace Tests\Unit;

use App\Jobs\CheckAddressJob;
use App\Models\Address;
use App\Models\Site;
use App\Services\SnapshotChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CheckAddressJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_runs_snapshot_checker_for_address(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
            'schedule_enabled' => true,
        ]);

        Http::fake([
            'https://api.example.com/health' => Http::response(['ok' => true], 200),
        ]);

        (new CheckAddressJob($address))->handle(app(SnapshotChecker::class));

        $this->assertSame(1, $address->snapshots()->count());
        $this->assertNotNull($address->fresh()->last_checked_at);
    }

    public function test_job_uses_rate_limited_middleware(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        $middleware = (new CheckAddressJob($address))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimited::class, $middleware[0]);
    }

    public function test_job_is_dispatched_to_the_site_queue(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        $job = new CheckAddressJob($address);

        $this->assertSame('site-'.$site->id, $job->queue);
        $this->assertSame('site-'.$site->id, CheckAddressJob::queueNameForSite($site->id));
        $this->assertSame($site->id, CheckAddressJob::siteIdFromQueueName($job->queue));
    }

    public function test_job_passes_check_run_id_to_snapshot_checker(): void
    {
        config(['checking.delay_seconds' => 0]);

        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        $checker = Mockery::mock(SnapshotChecker::class);
        $checker->shouldReceive('check')->once()->with(
            Mockery::on(fn (Address $a) => $a->is($address)),
            42,
        );

        (new CheckAddressJob($address, 42))->handle($checker);
    }

    public function test_job_respects_zero_delay_without_sleeping(): void
    {
        config(['checking.delay_seconds' => 0]);

        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        $checker = Mockery::mock(SnapshotChecker::class);
        $checker->shouldReceive('check')->once()->with(
            Mockery::on(fn (Address $a) => $a->is($address)),
            null,
        );

        $started = hrtime(true);
        (new CheckAddressJob($address))->handle($checker);
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;

        $this->assertLessThan(500, $elapsedMs);
    }

    public function test_dispatch_for_site_spaces_jobs_by_requests_per_minute(): void
    {
        config(['checking.delay_seconds' => 1]);
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
            'requests_per_minute' => 30,
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);

        CheckAddressJob::dispatchForSite(
            $site,
            $site->addresses()->orderBy('id')->get(),
            7,
        );

        Queue::assertPushed(CheckAddressJob::class, 2);

        $delays = [];
        Queue::assertPushed(CheckAddressJob::class, function (CheckAddressJob $job) use (&$delays): bool {
            $delays[] = $job->delay;

            return true;
        });

        $this->assertNull($delays[0]);
        $this->assertNotNull($delays[1]);
        $this->assertEqualsWithDelta(2, now()->diffInSeconds($delays[1], true), 0.2);
    }
}
