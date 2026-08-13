<?php

namespace Tests\Unit;

use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\CheckStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_average_response_time_and_errors_for_address(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
            'schedule_enabled' => true,
        ]);

        $this->createSnapshot($address, 100, null);
        $this->createSnapshot($address, 200, null);
        $this->createSnapshot($address, 300, 'timeout');

        $stats = app(CheckStats::class)->forAddress($address);

        $this->assertSame(3, $stats['checks_count']);
        $this->assertSame(200, $stats['avg_response_time_ms']);
        $this->assertSame(1, $stats['error_count']);
        $this->assertSame(0.33, $stats['avg_errors']);
    }

    public function test_computes_average_errors_per_schedule_run(): void
    {
        $site = Site::query()->create([
            'name' => 'Scheduled',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '5m',
        ]);
        $a1 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
            'schedule_enabled' => true,
        ]);
        $a2 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
            'schedule_enabled' => true,
        ]);
        $manual = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/manual',
            'schedule_enabled' => false,
        ]);

        // Run 1: one error among two scheduled addresses
        $this->createSnapshot($a1, 100, null, '2026-08-01 10:00:10');
        $this->createSnapshot($a2, 150, 'fail', '2026-08-01 10:00:12');

        // Run 2: two errors
        $this->createSnapshot($a1, 120, 'fail', '2026-08-01 10:05:10');
        $this->createSnapshot($a2, 180, 'fail', '2026-08-01 10:05:11');

        // Manual address should be ignored for schedule stats
        $this->createSnapshot($manual, 999, 'fail', '2026-08-01 10:00:10');

        $stats = app(CheckStats::class)->forSite($site, scheduledOnly: true);

        $this->assertSame(4, $stats['checks_count']);
        $this->assertSame(138, $stats['avg_response_time_ms']); // (100+150+120+180)/4
        $this->assertSame(3, $stats['error_count']);
        $this->assertSame(2, $stats['runs_count']);
        $this->assertSame(1.5, $stats['avg_errors_per_run']); // (1+2)/2
    }

    public function test_computes_average_latest_response_time_for_site(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $a1 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        $a2 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);

        $this->createSnapshot($a1, 100, null, '2026-08-01 10:00:00');
        $this->createSnapshot($a1, 400, null, '2026-08-01 11:00:00'); // latest for a1
        $this->createSnapshot($a2, 200, null, '2026-08-01 10:00:00');
        $this->createSnapshot($a2, 300, null, '2026-08-01 11:00:00'); // latest for a2

        $stats = app(CheckStats::class)->forSite($site);

        $this->assertSame(4, $stats['checks_count']);
        $this->assertSame(250, $stats['avg_response_time_ms']); // all four
        $this->assertSame(350, $stats['avg_latest_response_time_ms']); // (400+300)/2
        $this->assertSame(2, $stats['latest_checks_count']);
    }

    public function test_address_chart_uses_selected_period_sample(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
        ]);

        $this->createSnapshot($address, 500, null, now()->subHours(30)->toDateTimeString());
        $this->createSnapshot($address, 100, null, now()->subHours(2)->toDateTimeString());
        $this->createSnapshot($address, 200, null, now()->subMinutes(10)->toDateTimeString());

        $chart6h = app(CheckStats::class)->responseTimeChartForAddress($address, '6h');
        $this->assertSame('6h', $chart6h['period']);
        $this->assertTrue($chart6h['has_data']);
        $this->assertSame([100, 200], $chart6h['values']);
        $this->assertSame(150, $chart6h['avg_response_time_ms']);

        $chart48h = app(CheckStats::class)->responseTimeChartForAddress($address, '48h');
        $this->assertSame([500, 100, 200], $chart48h['values']);
        $this->assertSame(267, $chart48h['avg_response_time_ms']);
    }

    public function test_site_chart_averages_all_addresses_in_period(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $a1 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        $a2 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);

        $bucket = now()->subMinutes(20)->format('Y-m-d H:i:00');
        $this->createSnapshot($a1, 100, null, $bucket);
        $this->createSnapshot($a2, 300, null, $bucket);

        $chart = app(CheckStats::class)->responseTimeChartForSite($site, '6h');

        $this->assertSame('site', $chart['mode']);
        $this->assertTrue($chart['has_data']);
        $this->assertSame([200], $chart['values']);
        $this->assertSame(200, $chart['avg_response_time_ms']);
        $this->assertSame(2, $chart['checks_count']);
    }

    public function test_site_and_address_pages_show_response_time_chart(): void
    {
        $site = Site::query()->create([
            'name' => 'Chart Site',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Users',
            'endpoint' => '/users',
        ]);
        $this->createSnapshot($address, 120, null);

        $this->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Графік')
            ->assertSeeLivewire(\App\Livewire\Charts\ResponseTimeChartModal::class)
            ->assertSee('site-response-time-chart');

        $this->get("/sites/{$site->id}/addresses/{$address->id}")
            ->assertOk()
            ->assertSee('Графік')
            ->assertSee('address-response-time-chart');
    }

    public function test_site_page_shows_average_stats(): void
    {
        $site = Site::query()->create([
            'name' => 'Stats Site',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Users',
            'endpoint' => '/users',
            'schedule_enabled' => true,
        ]);
        $this->createSnapshot($address, 100, null);
        $this->createSnapshot($address, 300, 'boom');

        $this->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Середні показники перевірок')
            ->assertSee('Сер. час (розклад)')
            ->assertSee('Сер. помилок / запуск')
            ->assertSee('Переглянути помилки')
            ->assertSeeLivewire(\App\Livewire\Sites\ErrorSnapshotsModal::class)
            ->assertSee('Сер. час (остання)')
            ->assertSee('200 ms')
            ->assertSee('Сер. час');
    }

    public function test_error_snapshots_for_site_lists_schedule_errors_only(): void
    {
        $site = Site::query()->create([
            'name' => 'Errors',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
        ]);
        $scheduled = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Scheduled',
            'endpoint' => '/scheduled',
            'schedule_enabled' => true,
        ]);
        $manualOnly = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Manual',
            'endpoint' => '/manual',
            'schedule_enabled' => false,
        ]);

        $scheduleRun = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $manualRun = CheckRun::start($site, CheckRun::SOURCE_MANUAL);

        $keep = $this->createSnapshot($scheduled, 100, 'schedule fail', '2026-08-01 10:00:00', $scheduleRun->id);
        $this->createSnapshot($scheduled, 100, null, '2026-08-01 10:01:00', $scheduleRun->id);
        $this->createSnapshot($scheduled, 100, 'manual fail', '2026-08-01 10:02:00', $manualRun->id);
        $this->createSnapshot($manualOnly, 100, 'ignored', '2026-08-01 10:03:00', $scheduleRun->id);

        $errors = app(CheckStats::class)->errorSnapshotsForSite($site, scheduledOnly: true)->get();

        $this->assertCount(1, $errors);
        $this->assertTrue($errors->contains('id', $keep->id));
    }

    public function test_long_running_check_counts_as_single_run_and_chart_point(): void
    {
        $site = Site::query()->create([
            'name' => 'Long run',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
        ]);
        $a1 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
            'schedule_enabled' => true,
        ]);
        $a2 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
            'schedule_enabled' => true,
        ]);
        $a3 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/three',
            'schedule_enabled' => true,
        ]);

        $run = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);

        // Same logical run spanning three different minutes
        $this->createSnapshot($a1, 100, null, now()->subMinutes(12)->toDateTimeString(), $run->id);
        $this->createSnapshot($a2, 200, 'fail', now()->subMinutes(11)->toDateTimeString(), $run->id);
        $this->createSnapshot($a3, 300, null, now()->subMinutes(10)->toDateTimeString(), $run->id);

        $stats = app(CheckStats::class)->forSite($site, scheduledOnly: true);
        $this->assertSame(3, $stats['checks_count']);
        $this->assertSame(1, $stats['runs_count']);
        $this->assertSame(1.0, $stats['avg_errors_per_run']);
        $this->assertSame(200, $stats['avg_response_time_per_run_ms']);

        $chart = app(CheckStats::class)->responseTimeChartForSite($site, '6h');
        $this->assertSame(1, $chart['points_count']);
        $this->assertSame([200], $chart['values']);
        $this->assertSame([3], $chart['counts']);
    }

    public function test_manual_run_is_excluded_from_schedule_run_count(): void
    {
        $site = Site::query()->create([
            'name' => 'Mixed',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
            'schedule_enabled' => true,
        ]);

        $scheduleRun = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $manualRun = CheckRun::start($site, CheckRun::SOURCE_MANUAL);

        $this->createSnapshot($address, 100, null, '2026-08-01 10:00:00', $scheduleRun->id);
        $this->createSnapshot($address, 400, 'fail', '2026-08-01 10:10:00', $manualRun->id);

        $stats = app(CheckStats::class)->forSite($site, scheduledOnly: true);
        $this->assertSame(2, $stats['checks_count']);
        $this->assertSame(1, $stats['runs_count']);
        $this->assertSame(0.0, $stats['avg_errors_per_run']);
    }

    private function createSnapshot(
        Address $address,
        int $responseTimeMs,
        ?string $errorMessage,
        ?string $createdAt = null,
        ?int $checkRunId = null,
    ): Snapshot {
        $snapshot = Snapshot::query()->create([
            'address_id' => $address->id,
            'check_run_id' => $checkRunId,
            'status_code' => $errorMessage ? null : 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => $responseTimeMs,
            'error_message' => $errorMessage,
        ]);

        if ($createdAt !== null) {
            Snapshot::query()->whereKey($snapshot->id)->update(['created_at' => $createdAt]);
            $snapshot->refresh();
        }

        return $snapshot;
    }
}
