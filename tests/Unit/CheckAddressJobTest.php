<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\CheckAddressJob;
use App\Jobs\RestartSiteCheckJob;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Services\SnapshotChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\TestCase;

class CheckAddressJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_job_runs_snapshot_checker_for_address(): void
    {
        $site = Site::factory()->create([
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

    public function test_job_is_assigned_to_the_site_queue(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        $job = new CheckAddressJob($address);

        $this->assertSame($site->id, $job->siteId);
        $this->assertSame(Site::checkQueueName($site->id), $job->queue);
    }

    public function test_job_retries_too_many_requests_before_saving_snapshot(): void
    {
        // Arrange
        Sleep::fake();
        config([
            'checking.delay_seconds' => 0,
            'checking.too_many_requests_retries' => 3,
            'checking.too_many_requests_backoff_ms' => 1,
        ]);

        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/page',
            'schedule_enabled' => true,
        ]);

        Http::fake([
            'https://api.example.com/page' => Http::sequence()
                ->push('Too Many Requests', 429)
                ->push(['ok' => true], 200),
        ]);

        // Act
        (new CheckAddressJob($address))->handle(app(SnapshotChecker::class));

        // Assert
        $snapshot = $address->snapshots()->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(1, $address->snapshots()->count());
        $this->assertSame(200, $snapshot->status_code);
    }

    public function test_job_spaces_checks_using_site_requests_per_minute(): void
    {
        // Arrange
        Sleep::fake();
        config([
            'checking.delay_seconds' => 0,
            'checking.requests_per_minute' => 0,
        ]);

        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
            'requests_per_minute' => 120,
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        Http::fake([
            'https://api.example.com/health' => Http::response(['ok' => true], 200),
        ]);

        $checker = app(SnapshotChecker::class);

        // Act
        (new CheckAddressJob($address))->handle($checker);
        (new CheckAddressJob($address))->handle($checker);

        // Assert
        Sleep::assertSleptTimes(1);
        $this->assertSame(2, $address->snapshots()->count());
    }

    public function test_job_uses_rate_limited_middleware(): void
    {
        $site = Site::factory()->create([
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

    public function test_job_keeps_retrying_after_rate_limit_releases(): void
    {
        // Arrange
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);
        $job = new CheckAddressJob($address);

        // Act / Assert
        $this->assertSame(25, $job->tries);
        $this->assertSame(3, $job->maxExceptions);
        $this->assertGreaterThan(
            now()->addHour()->getTimestamp(),
            $job->retryUntil()->getTimestamp(),
        );
    }

    public function test_job_passes_check_run_id_to_snapshot_checker(): void
    {
        config(['checking.delay_seconds' => 0]);

        $site = Site::factory()->create([
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

        $site = Site::factory()->create([
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

    public function test_last_job_of_a_run_queues_chained_restart_when_enabled(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-08-19 12:00:00');
        config(['checking.delay_seconds' => 0, 'checking.chain_delay_seconds' => 60]);

        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => Site::SCHEDULE_INTERVAL_AFTER,
        ]);
        $first = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        $second = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);

        Http::fake([
            'https://api.example.com/one' => Http::response(['ok' => true], 200),
            'https://api.example.com/two' => Http::response(['ok' => true], 200),
        ]);

        $run = CheckAddressJob::dispatchForSite($site, CheckRun::SOURCE_MANUAL, [$first, $second]);

        $this->assertSame(2, $run->fresh()->remaining_jobs);
        Queue::assertPushed(CheckAddressJob::class, 2);
        Queue::assertNotPushed(RestartSiteCheckJob::class);

        (new CheckAddressJob($first, $run->id))->handle(app(SnapshotChecker::class));

        $this->assertSame(1, $run->fresh()->remaining_jobs);
        Queue::assertNotPushed(RestartSiteCheckJob::class);

        (new CheckAddressJob($second, $run->id))->handle(app(SnapshotChecker::class));

        $this->assertSame(0, $run->fresh()->remaining_jobs);
        Queue::assertPushed(RestartSiteCheckJob::class, 1);
        Queue::assertPushed(RestartSiteCheckJob::class, function (RestartSiteCheckJob $job) use ($site): bool {
            return $job->siteId === $site->id
                && $job->delay !== null
                && $job->delay->equalTo(now()->addSeconds(60));
        });
    }
}
