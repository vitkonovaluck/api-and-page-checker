<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Sites\Index;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SitesIndexAverageCheckTimesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sites_index_shows_average_check_times_for_each_window(): void
    {
        Carbon::setTestNow('2026-08-25 12:00:00');

        $site = Site::factory()->create([
            'name' => 'Timed Shop',
            'base_url' => 'https://api.example.com',
        ]);
        $first = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        $second = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);

        $previous = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $latest = CheckRun::start($site, CheckRun::SOURCE_MANUAL);

        $this->createSnapshot($first, 100, '2026-08-25 06:00:00', $previous->id);
        $this->createSnapshot($second, 200, '2026-08-25 06:00:01', $previous->id);
        $this->createSnapshot($first, 400, '2026-08-25 11:30:00', $latest->id);
        $this->createSnapshot($second, 500, '2026-08-25 11:30:01', $latest->id);

        Livewire::test(Index::class)
            ->assertSee('Сер. час перевірки')
            ->assertSee('остання: 450 ms')
            ->assertSee('1 год: 450 ms')
            ->assertSee('24 год: 300 ms')
            ->assertSee('разом: 300 ms');
    }

    public function test_sites_index_shows_em_dash_when_site_has_no_snapshots(): void
    {
        Site::factory()->create([
            'name' => 'Empty Shop',
            'base_url' => 'https://empty.example.com',
        ]);

        Livewire::test(Index::class)
            ->assertSee('Сер. час перевірки')
            ->assertSee('остання: —')
            ->assertSee('1 год: —')
            ->assertSee('24 год: —')
            ->assertSee('разом: —');
    }

    private function createSnapshot(
        Address $address,
        int $responseTimeMs,
        string $createdAt,
        int $checkRunId,
    ): Snapshot {
        $snapshot = Snapshot::query()->create([
            'address_id' => $address->id,
            'check_run_id' => $checkRunId,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => $responseTimeMs,
        ]);

        Snapshot::query()->whereKey($snapshot->id)->update(['created_at' => $createdAt]);

        return $snapshot->refresh();
    }
}
