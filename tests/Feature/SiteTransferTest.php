<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Sites\SiteSettingsModal;
use App\Models\Address;
use App\Models\Site;
use App\Models\SiteToken;
use App\Models\Snapshot;
use App\Services\SiteTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class SiteTransferTest extends TestCase
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

    public function test_transfer_actions_live_on_settings_and_site_modal(): void
    {
        $site = $this->makeSite();

        $this->get(route('sites.index'))
            ->assertOk()
            ->assertDontSee('Імпортувати')
            ->assertDontSee('Копіювати сайт');

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Експорт сайтів')
            ->assertSee('Імпорт сайтів')
            ->assertSee('Імпортувати')
            ->assertDontSee('Копіювати сайт')
            ->assertSee(route('sites.export-all'), false)
            ->assertSee(route('sites.import'), false);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->assertSee('Експортувати')
            ->assertSee('Копіювати сайт')
            ->assertSee(route('sites.export', $site), false);
    }

    public function test_site_settings_modal_copies_site_without_snapshots(): void
    {
        $site = $this->makeSite();

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->call('copy')
            ->assertRedirect();

        $copy = Site::query()->where('name', 'Demo Shop (копія)')->first();
        $this->assertNotNull($copy);
        $this->assertSame('https://api.example.com', $copy->base_url);
        $this->assertSame(1, $copy->addresses()->count());
        $this->assertSame(0, $copy->snapshots()->count());
        $this->assertSame(1, $site->snapshots()->count());
    }

    public function test_exporting_a_site_downloads_portable_json_without_history(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');
        $site = $this->makeSite();

        $response = $this->get(route('sites.export', $site));

        $response->assertOk()->assertDownload('demo-shop-'.$site->id.'-2026-08-18.json');

        $payload = json_decode($response->streamedContent(), true);
        $this->assertIsArray($payload);
        $this->assertSame(SiteTransferService::FORMAT, $payload['format']);
        $this->assertSame(1, $payload['version']);
        $this->assertCount(1, $payload['sites']);
        $this->assertSame('Demo Shop', $payload['sites'][0]['name']);
        $this->assertSame('https://api.example.com', $payload['sites'][0]['base_url']);
        $this->assertSame('/users', $payload['sites'][0]['addresses'][0]['endpoint']);
        $this->assertSame(['Authorization' => 'Bearer secret'], $payload['sites'][0]['addresses'][0]['request_headers']);
        $this->assertArrayNotHasKey('snapshots', $payload['sites'][0]['addresses'][0]);
        $this->assertArrayNotHasKey('schedule_last_run_at', $payload['sites'][0]);
        $this->assertArrayNotHasKey('last_checked_at', $payload['sites'][0]['addresses'][0]);
    }

    public function test_exporting_all_sites_includes_every_site(): void
    {
        $this->makeSite('First', 'https://one.example.com');
        $this->makeSite('Second', 'https://two.example.com');

        $response = $this->get(route('sites.export-all'));

        $response->assertOk()->assertDownload();
        $payload = json_decode($response->streamedContent(), true);
        $this->assertIsArray($payload);
        $this->assertCount(2, $payload['sites']);
        $this->assertSame(['First', 'Second'], array_column($payload['sites'], 'name'));
    }

    public function test_importing_json_creates_a_new_site_with_addresses(): void
    {
        $original = $this->makeSite();
        $json = app(SiteTransferService::class)->encode(
            app(SiteTransferService::class)->exportSite($original),
        );
        $file = UploadedFile::fake()->createWithContent('site.json', $json);

        $this->from(route('sites.index'))
            ->post(route('sites.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(2, Site::query()->count());
        $imported = Site::query()->whereKeyNot($original->id)->first();
        $this->assertNotNull($imported);
        $this->assertSame('Demo Shop', $imported->name);
        $this->assertSame('https://api.example.com', $imported->base_url);
        $this->assertTrue($imported->schedule_enabled);
        $this->assertSame('15m', $imported->schedule_interval);
        $this->assertNull($imported->schedule_last_run_at);
        $this->assertSame(5, $imported->requests_per_minute);

        $address = $imported->addresses()->first();
        $this->assertNotNull($address);
        $this->assertSame('/users', $address->endpoint);
        $this->assertSame('POST', $address->http_method);
        $this->assertSame('{"ok":true}', $address->request_body);
        $this->assertSame(['Authorization' => 'Bearer secret'], $address->request_headers);
        $this->assertNull($address->last_checked_at);
        $this->assertSame(0, $address->snapshots()->count());
    }

    public function test_copy_and_import_preserve_site_tokens_and_address_links(): void
    {
        $site = $this->makeSite();
        $token = SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'portable-secret',
        ]);
        $site->addresses()->first()?->update(['site_token_id' => $token->id]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site->fresh()])
            ->call('copy')
            ->assertRedirect();

        $copy = Site::query()->where('name', 'Demo Shop (копія)')->first();
        $this->assertNotNull($copy);
        $copiedToken = $copy->tokens()->first();
        $this->assertNotNull($copiedToken);
        $this->assertSame('Prod', $copiedToken->name);
        $this->assertSame('portable-secret', $copiedToken->value);
        $this->assertNotSame($token->id, $copiedToken->id);
        $this->assertSame($copiedToken->id, $copy->addresses()->first()?->site_token_id);

        $payload = app(SiteTransferService::class)->exportSite($site->fresh(['tokens', 'addresses.siteToken']));
        $this->assertSame('Prod', $payload['sites'][0]['tokens'][0]['name']);
        $this->assertSame('portable-secret', $payload['sites'][0]['tokens'][0]['value']);
        $this->assertSame('Prod', $payload['sites'][0]['addresses'][0]['token']);

        $imported = app(SiteTransferService::class)->importJson(
            app(SiteTransferService::class)->encode($payload),
            $site->user,
        )->first();

        $this->assertNotNull($imported);
        $importedToken = $imported->tokens()->first();
        $this->assertNotNull($importedToken);
        $this->assertSame('Prod', $importedToken->name);
        $this->assertSame('portable-secret', $importedToken->value);
        $this->assertSame($importedToken->id, $imported->addresses()->first()?->site_token_id);
    }

    public function test_import_does_not_replace_existing_sites(): void
    {
        $existing = $this->makeSite('Keep me', 'https://keep.example.com');
        $payload = app(SiteTransferService::class)->exportSite($existing);
        $payload['sites'][0]['name'] = 'Incoming';
        $file = UploadedFile::fake()->createWithContent(
            'site.json',
            app(SiteTransferService::class)->encode($payload),
        );

        $this->post(route('sites.import'), ['file' => $file])->assertRedirect();

        $this->assertTrue(Site::query()->where('name', 'Keep me')->exists());
        $this->assertTrue(Site::query()->where('name', 'Incoming')->exists());
        $this->assertSame(2, Site::query()->count());
    }

    public function test_import_rejects_invalid_json(): void
    {
        $file = UploadedFile::fake()->createWithContent('broken.json', '{not-json');

        $this->from(route('settings.index'))
            ->post(route('sites.import'), ['file' => $file])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Site::query()->count());
    }

    public function test_import_rejects_unknown_format(): void
    {
        $file = UploadedFile::fake()->createWithContent('other.json', json_encode([
            'format' => 'something-else',
            'version' => 1,
            'sites' => [['name' => 'X', 'base_url' => 'https://api.example.com']],
        ]));

        $this->from(route('sites.index'))
            ->post(route('sites.import'), ['file' => $file])
            ->assertRedirect(route('sites.index'))
            ->assertSessionHasErrors('file');
    }

    public function test_artisan_export_and_import_roundtrip(): void
    {
        $site = $this->makeSite();
        $path = storage_path('framework/testing/sites-export.json');
        File::ensureDirectoryExists(dirname($path));

        try {
            $this->artisan('sites:export', ['site' => $site->id, '--path' => $path])
                ->assertSuccessful();

            $site->delete();
            $this->assertSame(0, Site::query()->count());

            $this->artisan('sites:import', [
                'file' => $path,
                '--user' => $site->user->email,
            ])
                ->assertSuccessful();

            $imported = Site::query()->first();
            $this->assertNotNull($imported);
            $this->assertSame('Demo Shop', $imported->name);
            $this->assertSame(1, $imported->addresses()->count());
        } finally {
            File::delete($path);
        }
    }

    public function test_artisan_export_fails_for_missing_site(): void
    {
        $this->artisan('sites:export', ['site' => 999])
            ->assertFailed();
    }

    private function makeSite(string $name = 'Demo Shop', string $baseUrl = 'https://api.example.com'): Site
    {
        $site = Site::factory()->create([
            'name' => $name,
            'base_url' => $baseUrl,
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
            'schedule_last_run_at' => now(),
            'requests_per_minute' => 5,
        ]);

        $address = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Users',
            'endpoint' => '/users',
            'http_method' => 'POST',
            'schedule_enabled' => true,
            'request_headers' => ['Authorization' => 'Bearer secret'],
            'request_body' => '{"ok":true}',
            'last_checked_at' => now(),
        ]);

        Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 10,
        ]);

        return $site->load('addresses');
    }
}
