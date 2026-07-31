<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\DiffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiSnapshotCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_sites_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Список сайтів');
    }

    public function test_can_create_site_with_optional_endpoint(): void
    {
        $this->post('/sites', [
            'name' => 'Demo Shop',
            'base_url' => 'https://api.example.com',
            'endpoint' => '/users',
            'address_name' => 'Users',
        ])->assertRedirect();

        $site = Site::query()->first();
        $this->assertNotNull($site);
        $this->assertSame('Demo Shop', $site->name);
        $this->assertSame('https://api.example.com', $site->base_url);
        $this->assertDatabaseCount('addresses', 1);
        $this->assertSame('/users', $site->addresses()->first()->endpoint);
        $this->assertSame('https://api.example.com/users', $site->addresses()->first()->fullUrl());
    }

    public function test_can_check_single_address_and_detect_json_changes(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Data',
            'endpoint' => '/data',
        ]);

        Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['version' => 1, 'name' => 'old']),
            'body_hash' => hash('sha256', json_encode(['version' => 1, 'name' => 'old'])),
            'response_time_ms' => 100,
        ]);

        Http::fake([
            'https://api.example.com/data' => Http::response(['version' => 2, 'name' => 'new'], 200),
        ]);

        $this->post("/sites/{$site->id}/addresses/{$address->id}/check")
            ->assertRedirect("/sites/{$site->id}/addresses/{$address->id}");

        $latest = $address->snapshots()->orderByDesc('id')->first();
        $previous = $latest->previous();
        $diff = app(DiffService::class)->compare($previous, $latest);

        $this->assertTrue($diff['has_changes']);
        $this->assertSame('json', $diff['body']['type']);
        $paths = array_column($diff['body']['changes'], 'path');
        $this->assertContains('version', $paths);
        $this->assertContains('name', $paths);
        $this->assertNotNull($address->fresh()->last_checked_at);
    }

    public function test_check_all_site_addresses_creates_snapshot_for_each(): void
    {
        $site = Site::query()->create([
            'name' => 'Multi',
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

        Http::fake([
            'https://api.example.com/one' => Http::response(['id' => 1], 200),
            'https://api.example.com/two' => Http::response(['id' => 2], 200),
        ]);

        $this->post("/sites/{$site->id}/check")
            ->assertRedirect("/sites/{$site->id}");

        $this->assertDatabaseCount('snapshots', 2);
        $this->assertSame(1, $a1->snapshots()->count());
        $this->assertSame(1, $a2->snapshots()->count());
        $this->assertNotNull($a1->fresh()->last_checked_at);
        $this->assertNotNull($a2->fresh()->last_checked_at);
    }

    public function test_check_all_with_no_addresses_shows_message(): void
    {
        $site = Site::query()->create([
            'name' => 'Empty',
            'base_url' => 'https://api.example.com',
        ]);

        $this->post("/sites/{$site->id}/check")
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Немає адрес для перевірки.');
    }

    public function test_can_copy_site_without_snapshots(): void
    {
        $site = Site::query()->create([
            'name' => 'Original',
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
        Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 10,
        ]);

        $this->post("/sites/{$site->id}/copy")
            ->assertRedirect();

        $copy = Site::query()->where('name', 'Original (копія)')->first();
        $this->assertNotNull($copy);
        $this->assertSame('https://api.example.com', $copy->base_url);
        $this->assertTrue($copy->schedule_enabled);
        $this->assertSame('15m', $copy->schedule_interval);
        $this->assertNull($copy->schedule_last_run_at);
        $this->assertSame(1, $copy->addresses()->count());
        $this->assertSame('/users', $copy->addresses()->first()->endpoint);
        $this->assertSame(0, $copy->addresses()->first()->snapshots()->count());
        $this->assertSame(1, $address->snapshots()->count());
    }

    public function test_scheduled_command_checks_due_sites(): void
    {
        $site = Site::query()->create([
            'name' => 'Scheduled',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '5m',
        ]);
        $included = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/included',
            'schedule_enabled' => true,
        ]);
        $excluded = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/excluded',
            'schedule_enabled' => false,
        ]);

        Http::fake([
            'https://api.example.com/included' => Http::response(['ok' => true], 200),
            'https://api.example.com/excluded' => Http::response(['ok' => true], 200),
        ]);

        Artisan::call('sites:run-scheduled');

        $this->assertSame(1, $included->snapshots()->count());
        $this->assertSame(0, $excluded->snapshots()->count());
        $this->assertNotNull($site->fresh()->schedule_last_run_at);
    }

    public function test_settings_page_is_available(): void
    {
        $this->get('/settings')
            ->assertOk()
            ->assertSee('Бекап бази даних');
    }

    public function test_can_delete_snapshot(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
        ]);
        $snapshot = Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 10,
        ]);

        $this->delete("/sites/{$site->id}/addresses/{$address->id}/snapshots/{$snapshot->id}")
            ->assertRedirect("/sites/{$site->id}/addresses/{$address->id}")
            ->assertSessionHas('success', 'Знімок видалено.');

        $this->assertDatabaseMissing('snapshots', ['id' => $snapshot->id]);
    }
}
