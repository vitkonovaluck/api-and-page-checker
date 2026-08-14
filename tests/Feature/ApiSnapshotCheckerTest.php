<?php

namespace Tests\Feature;

use App\Jobs\CheckAddressJob;
use App\Livewire\Addresses\AddressSettingsModal;
use App\Livewire\Addresses\CreateAddressModal;
use App\Livewire\Charts\ResponseTimeChartModal;
use App\Livewire\Sites\AddressListModal;
use App\Livewire\Sites\CreateSiteModal;
use App\Livewire\Sites\ErrorSnapshotsModal;
use App\Livewire\Sites\Index as SitesIndex;
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

    public function test_index_marks_only_the_busy_site_check_as_busy(): void
    {
        config(['queue.default' => 'database']);

        $busySite = Site::query()->create([
            'name' => 'Busy Site',
            'base_url' => 'https://busy.example.com',
        ]);
        $idleSite = Site::query()->create([
            'name' => 'Idle Site',
            'base_url' => 'https://idle.example.com',
        ]);
        $busyAddress = Address::query()->create([
            'site_id' => $busySite->id,
            'endpoint' => '/busy',
        ]);
        Address::query()->create([
            'site_id' => $idleSite->id,
            'endpoint' => '/idle',
        ]);

        CheckAddressJob::dispatch($busyAddress);

        Livewire::test(SitesIndex::class)
            ->assertSet('busySiteIds', [$busySite->id]);
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

    public function test_check_all_dispatches_a_job_per_address(): void
    {
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Queued manual',
            'base_url' => 'https://api.example.com',
        ]);
        $a1 = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);

        $this->post("/sites/{$site->id}/check")
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Перевірку 2 адрес поставлено в чергу.');

        Queue::assertPushed(CheckAddressJob::class, 2);
        Queue::assertPushedOn('site-'.$site->id, CheckAddressJob::class);
        Queue::assertPushed(CheckAddressJob::class, function (CheckAddressJob $job) use ($a1) {
            return $job->address->is($a1) && $job->checkRunId !== null;
        });
        $this->assertDatabaseCount('check_runs', 1);
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

        Cache::put(CheckingGuard::manualCacheKey($site->id), true, 60);

        Http::fake([
            'https://api.example.com/data' => Http::response(['ok' => true], 200),
        ]);

        $this->from(route('addresses.show', [$site, $address]))
            ->post("/sites/{$site->id}/addresses/{$address->id}/check")
            ->assertRedirect(route('addresses.show', [$site, $address]))
            ->assertSessionHas('error');

        $this->assertSame(0, $address->snapshots()->count());
    }

    public function test_manual_check_on_another_site_is_allowed_while_one_is_busy(): void
    {
        $busySite = Site::query()->create([
            'name' => 'Busy',
            'base_url' => 'https://busy.example.com',
        ]);
        $freeSite = Site::query()->create([
            'name' => 'Free',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $freeSite->id,
            'endpoint' => '/data',
        ]);

        Cache::put(CheckingGuard::manualCacheKey($busySite->id), true, 60);

        Http::fake([
            'https://api.example.com/data' => Http::response(['ok' => true], 200),
        ]);

        $this->post("/sites/{$freeSite->id}/addresses/{$address->id}/check")
            ->assertRedirect(route('addresses.show', [$freeSite, $address]))
            ->assertSessionHas('success');

        $this->assertSame(1, $address->snapshots()->count());
    }

    public function test_scheduled_command_skips_when_manual_check_is_running(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:15:00'));
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

        Cache::put(CheckingGuard::manualCacheKey($site->id), true, 60);

        Artisan::call('sites:run-scheduled');

        Queue::assertNothingPushed();
        $this->assertNull($site->fresh()->schedule_last_run_at);

        Carbon::setTestNow();
    }

    public function test_scheduled_command_still_queues_other_sites_when_one_is_busy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:15:00'));
        Queue::fake();

        $busySite = Site::query()->create([
            'name' => 'Busy',
            'base_url' => 'https://busy.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '5m',
        ]);
        Address::query()->create([
            'site_id' => $busySite->id,
            'endpoint' => '/busy',
            'schedule_enabled' => true,
        ]);

        $freeSite = Site::query()->create([
            'name' => 'Free',
            'base_url' => 'https://api.example.com',
            'schedule_enabled' => true,
            'schedule_interval' => '5m',
        ]);
        $freeAddress = Address::query()->create([
            'site_id' => $freeSite->id,
            'endpoint' => '/free',
            'schedule_enabled' => true,
        ]);

        Cache::put(CheckingGuard::manualCacheKey($busySite->id), true, 60);

        Artisan::call('sites:run-scheduled');

        Queue::assertPushed(CheckAddressJob::class, 1);
        Queue::assertPushedOn('site-'.$freeSite->id, CheckAddressJob::class);
        Queue::assertPushed(CheckAddressJob::class, function (CheckAddressJob $job) use ($freeAddress) {
            return $job->address->is($freeAddress);
        });
        $this->assertNull($busySite->fresh()->schedule_last_run_at);
        $this->assertNotNull($freeSite->fresh()->schedule_last_run_at);

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
            'requests_per_minute' => 12,
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
        $this->assertSame(12, $copy->requests_per_minute);
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

    public function test_bulk_create_allows_existing_endpoints_with_different_request_options(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/users',
            'http_method' => 'GET',
            'request_headers' => ['Accept' => 'application/json'],
        ]);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->call('open')
            ->set('endpoints', '/users')
            ->set('http_method', 'POST')
            ->set('headers', [
                ['name' => 'Authorization', 'value' => 'Bearer token'],
            ])
            ->set('request_body', '{"ok":true}')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('sites.show', $site));

        $addresses = $site->addresses()->orderBy('id')->get();
        $this->assertCount(2, $addresses);
        $this->assertSame('/users', $addresses[0]->endpoint);
        $this->assertSame('/users', $addresses[1]->endpoint);
        $this->assertSame('GET', $addresses[0]->http_method);
        $this->assertSame('POST', $addresses[1]->http_method);
        $this->assertSame(['Authorization' => 'Bearer token'], $addresses[1]->request_headers);
        $this->assertSame('{"ok":true}', $addresses[1]->request_body);
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

    public function test_error_snapshots_modal_lists_failed_schedule_requests(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
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
            'status_code' => null,
            'headers' => [],
            'body' => '',
            'body_hash' => hash('sha256', ''),
            'response_time_ms' => 12,
            'error_message' => 'Connection timed out',
        ]);

        Livewire::test(ErrorSnapshotsModal::class, ['site' => $site])
            ->call('open')
            ->assertSet('show', true)
            ->assertSee('Помилкові запити (розклад)')
            ->assertSee('Connection timed out')
            ->assertSee('Users')
            ->assertSee('/users');
    }

    public function test_address_list_modal_lists_site_endpoints(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Car Service',
            'endpoint' => '/api/car-service/type',
            'http_method' => 'GET',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Contact Form',
            'endpoint' => '/api/contact/form/send',
            'http_method' => 'POST',
        ]);

        $this->get("/sites/{$site->id}")
            ->assertOk()
            ->assertSee('Список адрес')
            ->assertSeeLivewire(AddressListModal::class);

        Livewire::test(AddressListModal::class, ['site' => $site])
            ->call('open')
            ->assertSet('show', true)
            ->assertSee('Адреси для перевірки')
            ->assertSee('GET /api/car-service/type')
            ->assertSee('POST /api/contact/form/send')
            ->assertDontSee('Car Service');
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
        $this->assertDatabaseCount('check_runs', 1);
        $this->assertNotNull($included->snapshots()->first()->check_run_id);

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
        Queue::assertPushedOn('site-'.$site->id, CheckAddressJob::class);
        Queue::assertPushed(CheckAddressJob::class, function (CheckAddressJob $job) use ($included) {
            return $job->address->is($included) && $job->checkRunId !== null;
        });
        $this->assertNotNull($site->fresh()->schedule_last_run_at);
        $this->assertDatabaseCount('check_runs', 1);

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

    public function test_can_update_site_requests_per_minute_from_settings(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->set('requestsPerMinute', 12)
            ->call('save')
            ->assertRedirect("/sites/{$site->id}");

        $this->assertSame(12, $site->fresh()->requests_per_minute);
    }

    public function test_updating_requests_per_minute_does_not_close_settings_modal(): void
    {
        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->call('open')
            ->assertSet('show', true)
            ->set('requestsPerMinute', 12)
            ->assertSet('show', true)
            ->assertSet('requestsPerMinute', 12);
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
