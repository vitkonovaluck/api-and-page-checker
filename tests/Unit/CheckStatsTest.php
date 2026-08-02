<?php

namespace Tests\Unit;

use App\Models\Address;
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
            ->assertSee('200 ms')
            ->assertSee('Сер. час');
    }

    private function createSnapshot(
        Address $address,
        int $responseTimeMs,
        ?string $errorMessage,
        ?string $createdAt = null,
    ): Snapshot {
        $snapshot = Snapshot::query()->create([
            'address_id' => $address->id,
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
