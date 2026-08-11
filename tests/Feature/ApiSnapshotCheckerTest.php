<?php

namespace Tests\Feature;

use App\Jobs\CheckAddressJob;
use App\Livewire\Addresses\AddressSettingsModal;
use App\Livewire\Addresses\CreateAddressModal;
use App\Livewire\Charts\ResponseTimeChartModal;
use App\Livewire\Sites\CreateSiteModal;
use App\Livewire\Sites\Show;
use App\Livewire\Sites\SiteSettingsModal;
use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\CheckingGuard;
use App\Services\DiffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ApiSnapshotCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_index_shows_sites_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Список сайтів')
            ->assertSeeLivewire(CreateSiteModal::class);
    }

    public function test_can_create_site_with_optional_endpoint(): void
    {
        Livewire::test(CreateSiteModal::class)
            ->set('name', 'Demo Shop')
            ->set('base_url', 'https://api.example.com')
            ->set('endpoint', '/users')
            ->set('address_name', 'Users')
            ->call('save')
            ->assertRedirect();

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

    public function test_manual_check_is_blocked_while_another_check_is_running(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
        ]);

        Cache::put(CheckingGuard::MANUAL_KEY, true, 60);

        Http::fake([
            'https://api.example.com/data' => Http::response(['ok' => true], 200),
        ]);

        $this->from(route('addresses.show', [$site, $address]))
            ->post("/sites/{$site->id}/addresses/{$address->id}/check")
            ->assertRedirect(route('addresses.show', [$site, $address]))
            ->assertSessionHas('error');

        $this->assertSame(0, $address->snapshots()->count());
    }

    public function test_scheduled_command_skips_when_manual_check_is_running(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:15:00'));
        Cache::put(CheckingGuard::MANUAL_KEY, true, 60);
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Scheduled',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '5m',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/included',
            'schedule_enabled' => true,
        ]);

        Artisan::call('sites:run-scheduled');

        Queue::assertNothingPushed();
        $this->assertNull($site->fresh()->schedule_last_run_at);

        Carbon::setTestNow();
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
            'request_headers' => [
                'Authorization' => 'Bearer secret',
                'X-Custom' => 'yes',
            ],
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
        $copiedAddress = $copy->addresses()->first();
        $this->assertSame('/users', $copiedAddress->endpoint);
        $this->assertSame([
            'Authorization' => 'Bearer secret',
            'X-Custom' => 'yes',
        ], $copiedAddress->request_headers);
        $this->assertSame(0, $copiedAddress->snapshots()->count());
        $this->assertSame(1, $address->snapshots()->count());
    }

    public function test_can_create_address_with_request_headers(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->set('endpoints', '/secure')
            ->set('name', 'Secure')
            ->set('schedule_enabled', true)
            ->set('headers', [
                ['name' => 'Authorization', 'value' => 'Bearer token-123'],
                ['name' => '', 'value' => 'ignored'],
                ['name' => 'X-Api-Key', 'value' => 'abc'],
            ])
            ->call('save')
            ->assertRedirect("/sites/{$site->id}");

        $address = $site->addresses()->first();
        $this->assertNotNull($address);
        $this->assertSame('/secure', $address->endpoint);
        $this->assertSame('GET', $address->http_method);
        $this->assertSame([
            'Authorization' => 'Bearer token-123',
            'X-Api-Key' => 'abc',
        ], $address->request_headers);
    }

    public function test_can_bulk_create_addresses_with_shared_method_headers_and_body(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->set('endpoints', "/users\n/orders\n\n/products")
            ->set('name', 'Ignored for bulk')
            ->set('http_method', 'POST')
            ->set('request_body', '{"ok":true}')
            ->set('headers', [
                ['name' => 'Authorization', 'value' => 'Bearer shared'],
            ])
            ->call('save')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Додано 3 адрес.');

        $addresses = $site->addresses()->orderBy('endpoint')->get();
        $this->assertCount(3, $addresses);
        $this->assertSame(['/orders', '/products', '/users'], $addresses->pluck('endpoint')->all());

        foreach ($addresses as $address) {
            $this->assertNull($address->name);
            $this->assertSame('POST', $address->http_method);
            $this->assertSame('{"ok":true}', $address->request_body);
            $this->assertSame(['Authorization' => 'Bearer shared'], $address->request_headers);
        }
    }

    public function test_bulk_create_rejects_duplicate_endpoints_in_list(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->call('open')
            ->set('endpoints', "/users\n/users")
            ->call('save')
            ->assertHasErrors(['endpoints'])
            ->assertSet('show', true);

        $this->assertSame(0, $site->addresses()->count());
    }

    public function test_bulk_create_rejects_existing_endpoints(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/users',
        ]);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->call('open')
            ->set('endpoints', "/users\n/orders")
            ->call('save')
            ->assertHasErrors(['endpoints'])
            ->assertSet('show', true);

        $this->assertSame(1, $site->addresses()->count());
    }

    public function test_create_address_rejects_empty_endpoints_and_keeps_modal_open(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->call('open')
            ->set('endpoints', "   \n\n  ")
            ->call('save')
            ->assertHasErrors(['endpoints'])
            ->assertSet('show', true);

        $this->assertSame(0, $site->addresses()->count());
    }

    public function test_can_update_address_request_headers(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
            'request_headers' => ['Old' => 'value'],
        ]);

        Livewire::test(AddressSettingsModal::class, ['site' => $site, 'address' => $address])
            ->set('headers', [
                ['name' => 'Authorization', 'value' => 'Bearer new'],
            ])
            ->call('save')
            ->assertRedirect("/sites/{$site->id}/addresses/{$address->id}")
            ->assertSessionHas('success', 'Налаштування адреси збережено.');

        $this->assertSame(
            ['Authorization' => 'Bearer new'],
            $address->fresh()->request_headers,
        );
    }

    public function test_can_update_address_method_and_body(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
            'http_method' => 'GET',
        ]);

        Livewire::test(AddressSettingsModal::class, ['site' => $site, 'address' => $address])
            ->set('http_method', 'PUT')
            ->set('request_body', '{"name":"updated"}')
            ->call('save')
            ->assertRedirect("/sites/{$site->id}/addresses/{$address->id}");

        $address->refresh();
        $this->assertSame('PUT', $address->http_method);
        $this->assertSame('{"name":"updated"}', $address->request_body);

        Livewire::test(AddressSettingsModal::class, ['site' => $site, 'address' => $address])
            ->set('http_method', 'GET')
            ->call('save');

        $address->refresh();
        $this->assertSame('GET', $address->http_method);
        $this->assertNull($address->request_body);
    }

    public function test_response_time_chart_modal_switches_period(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
        ]);
        Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 42,
        ]);

        Livewire::test(ResponseTimeChartModal::class, [
            'mode' => 'site',
            'siteId' => $site->id,
            'title' => 'Історія часу відповіді адрес',
            'chartId' => 'site-response-time-chart',
        ])
            ->call('open')
            ->assertSet('show', true)
            ->assertSee('Період вибірки')
            ->assertSee('site-response-time-chart')
            ->call('setPeriod', '6h')
            ->assertSet('period', '6h');

        Livewire::test(ResponseTimeChartModal::class, [
            'mode' => 'address',
            'addressId' => $address->id,
            'title' => 'Історія часу відповіді',
            'chartId' => 'address-response-time-chart',
        ])
            ->call('open')
            ->assertSee('address-response-time-chart')
            ->call('setPeriod', '12h')
            ->assertSet('period', '12h');
    }

    public function test_check_sends_custom_request_headers(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/secure',
            'request_headers' => [
                'Authorization' => 'Bearer custom-token',
                'Accept' => 'application/xml',
            ],
        ]);

        Http::fake([
            'https://api.example.com/secure' => Http::response(['ok' => true], 200),
        ]);

        $this->post("/sites/{$site->id}/addresses/{$address->id}/check")
            ->assertRedirect("/sites/{$site->id}/addresses/{$address->id}");

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/secure'
                && $request->hasHeader('Authorization', 'Bearer custom-token')
                && $request->hasHeader('Accept', 'application/xml')
                && $request->hasHeader('User-Agent', 'API-Snapshot-Checker/1.0');
        });

        $this->assertSame(1, $address->snapshots()->count());
    }

    public function test_check_sends_post_with_request_body(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/items',
            'http_method' => 'POST',
            'request_body' => '{"title":"hello"}',
            'request_headers' => [
                'Authorization' => 'Bearer post-token',
            ],
        ]);

        Http::fake([
            'https://api.example.com/items' => Http::response(['id' => 1], 201),
        ]);

        $this->post("/sites/{$site->id}/addresses/{$address->id}/check")
            ->assertRedirect("/sites/{$site->id}/addresses/{$address->id}");

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/items'
                && $request->method() === 'POST'
                && $request->body() === '{"title":"hello"}'
                && $request->hasHeader('Authorization', 'Bearer post-token')
                && $request->hasHeader('Content-Type', 'application/json');
        });

        $this->assertSame(1, $address->snapshots()->count());
        $this->assertSame(201, $address->snapshots()->first()->status_code);
    }

    public function test_scheduled_command_queues_due_sites(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:15:00'));

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

        Carbon::setTestNow();
    }

    public function test_scheduled_command_dispatches_check_jobs_to_queue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:15:00'));
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Queued',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '5m',
        ]);
        $included = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/included',
            'schedule_enabled' => true,
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/excluded',
            'schedule_enabled' => false,
        ]);

        Artisan::call('sites:run-scheduled');

        Queue::assertPushed(CheckAddressJob::class, 1);
        Queue::assertPushed(CheckAddressJob::class, fn (CheckAddressJob $job) => $job->address->is($included));
        $this->assertNotNull($site->fresh()->schedule_last_run_at);

        Carbon::setTestNow();
    }

    public function test_scheduled_command_does_not_double_queue_when_run_twice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:15:00'));

        $site = Site::query()->create([
            'name' => 'Scheduled',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '5m',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/once',
            'schedule_enabled' => true,
        ]);

        Http::fake([
            'https://api.example.com/once' => Http::response(['ok' => true], 200),
        ]);

        Artisan::call('sites:run-scheduled');
        Artisan::call('sites:run-scheduled');

        $this->assertSame(1, $address->snapshots()->count());

        Carbon::setTestNow();
    }

    public function test_scheduled_command_waits_for_clock_aligned_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:07:00'));

        $site = Site::query()->create([
            'name' => 'Aligned',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/aligned',
            'schedule_enabled' => true,
        ]);

        Http::fake([
            'https://api.example.com/aligned' => Http::response(['ok' => true], 200),
        ]);

        Artisan::call('sites:run-scheduled');
        $this->assertSame(0, $address->snapshots()->count());
        $this->assertNull($site->fresh()->schedule_last_run_at);

        Carbon::setTestNow(Carbon::parse('2026-08-07 10:15:00'));
        Artisan::call('sites:run-scheduled');
        $this->assertSame(1, $address->snapshots()->count());

        Carbon::setTestNow();
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

    public function test_can_clear_all_site_snapshots_from_settings(): void
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

        foreach ([$a1, $a2] as $address) {
            Snapshot::query()->create([
                'address_id' => $address->id,
                'status_code' => 200,
                'headers' => [],
                'body' => '{}',
                'body_hash' => hash('sha256', '{}'),
                'response_time_ms' => 10,
            ]);
        }

        $otherSite = Site::query()->create([
            'name' => 'Other',
            'base_url' => 'https://other.example.com',
        ]);
        $otherAddress = Address::query()->create([
            'site_id' => $otherSite->id,
            'endpoint' => '/keep',
        ]);
        Snapshot::query()->create([
            'address_id' => $otherAddress->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 5,
        ]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->call('clearSnapshots')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Видалено знімків: 2.');

        $this->assertSame(0, $site->snapshots()->count());
        $this->assertSame(1, $otherSite->snapshots()->count());
    }

    public function test_site_show_highlights_changed_status_with_previous(): void
    {
        $site = Site::query()->create([
            'name' => 'Status Change',
            'base_url' => 'https://api.example.com',
        ]);
        $changed = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Changed',
            'endpoint' => '/changed',
        ]);
        $stable = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Stable',
            'endpoint' => '/stable',
        ]);

        Snapshot::query()->create([
            'address_id' => $changed->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 10,
        ]);
        Snapshot::query()->create([
            'address_id' => $changed->id,
            'status_code' => 500,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 20,
        ]);

        Snapshot::query()->create([
            'address_id' => $stable->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 30,
        ]);
        Snapshot::query()->create([
            'address_id' => $stable->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 12,
        ]);

        $changed->load(['latestSnapshot', 'previousSnapshot']);
        $this->assertSame(500, $changed->latestSnapshot->status_code);
        $this->assertSame(200, $changed->previousSnapshot->status_code);

        Livewire::test(Show::class, ['site' => $site])
            ->assertOk()
            ->assertSeeHtml('bg-red-100 text-red-800')
            ->assertSeeHtml('500')
            ->assertSeeHtml('(200)')
            ->assertSeeHtml('text-red-600')
            ->assertSeeHtml('↑')
            ->assertSeeHtml('text-emerald-600')
            ->assertSeeHtml('↓');
    }
}
