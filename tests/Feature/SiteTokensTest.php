<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Addresses\AddressSettingsModal;
use App\Livewire\Addresses\CreateAddressModal;
use App\Livewire\Sites\SiteSettingsModal;
use App\Models\Address;
use App\Models\Site;
use App\Models\SiteToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SiteTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_site_settings_saves_multiple_tokens_and_ignores_empty_rows(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->set('tokens', [
                ['id' => null, 'name' => 'Prod', 'value' => 'secret-one'],
                ['id' => null, 'name' => 'Admin', 'value' => 'secret-two'],
                ['id' => null, 'name' => '', 'value' => ''],
            ])
            ->call('save')
            ->assertRedirect(route('sites.show', $site));

        $tokens = $site->tokens()->orderBy('name')->get();
        $this->assertCount(2, $tokens);
        $this->assertSame('Admin', $tokens[0]->name);
        $this->assertSame('secret-two', $tokens[0]->value);
        $this->assertSame('Prod', $tokens[1]->name);
        $this->assertSame('secret-one', $tokens[1]->value);
    }

    public function test_site_settings_rejects_duplicate_token_names(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->set('tokens', [
                ['id' => null, 'name' => 'Prod', 'value' => 'one'],
                ['id' => null, 'name' => 'Prod', 'value' => 'two'],
            ])
            ->call('save')
            ->assertHasErrors(['tokens']);

        $this->assertSame(0, $site->tokens()->count());
    }

    public function test_saving_other_site_settings_keeps_existing_tokens(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'keep-me',
        ]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->set('requestsPerMinute', 8)
            ->call('save')
            ->assertRedirect(route('sites.show', $site));

        $token = $site->tokens()->first();
        $this->assertNotNull($token);
        $this->assertSame('Prod', $token->name);
        $this->assertSame('keep-me', $token->value);
        $this->assertSame(8, $site->fresh()->requests_per_minute);
    }

    public function test_address_can_connect_a_token_from_its_site(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $token = SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'connected-secret',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/secure',
        ]);

        Livewire::test(AddressSettingsModal::class, ['site' => $site, 'address' => $address])
            ->set('siteTokenId', $token->id)
            ->call('save')
            ->assertRedirect(route('addresses.show', [$site, $address]));

        $this->assertSame($token->id, $address->fresh()->site_token_id);
    }

    public function test_address_cannot_connect_a_token_from_another_site(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/secure',
        ]);
        $foreignToken = SiteToken::factory()->create([
            'name' => 'Foreign',
            'value' => 'other-secret',
        ]);

        Livewire::test(AddressSettingsModal::class, ['site' => $site, 'address' => $address])
            ->set('siteTokenId', $foreignToken->id)
            ->call('save')
            ->assertHasErrors(['siteTokenId']);

        $this->assertNull($address->fresh()->site_token_id);
    }

    public function test_create_address_applies_the_selected_site_token(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $token = SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'create-secret',
        ]);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->set('endpoints', "/one\n/two")
            ->set('siteTokenId', $token->id)
            ->call('save')
            ->assertRedirect(route('sites.show', $site));

        $addresses = $site->addresses()->orderBy('endpoint')->get();
        $this->assertCount(2, $addresses);
        $this->assertSame($token->id, $addresses[0]->site_token_id);
        $this->assertSame($token->id, $addresses[1]->site_token_id);
    }

    public function test_check_sends_connected_token_as_authorization_bearer(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $token = SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'connected-token',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/secure',
            'site_token_id' => $token->id,
            'request_headers' => ['Accept' => 'application/xml'],
        ]);

        Http::fake([
            'https://api.example.com/secure' => Http::response(['ok' => true], 200),
        ]);

        $this->post(route('addresses.check', [$site, $address]))
            ->assertRedirect(route('addresses.show', [$site, $address]));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/secure'
                && $request->hasHeader(SiteToken::HEADER_NAME, 'Bearer connected-token')
                && $request->hasHeader('Accept', 'application/xml');
        });
    }

    public function test_connected_token_overrides_manual_authorization_header(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $token = SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'site-token',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/secure',
            'site_token_id' => $token->id,
            'request_headers' => [SiteToken::HEADER_NAME => 'Bearer manual-token'],
        ]);

        Http::fake([
            'https://api.example.com/secure' => Http::response(['ok' => true], 200),
        ]);

        $this->post(route('addresses.check', [$site, $address]));

        Http::assertSent(fn ($request) => $request->hasHeader(SiteToken::HEADER_NAME, 'Bearer site-token'));
    }

    public function test_check_without_a_token_keeps_manual_headers(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/secure',
            'request_headers' => [SiteToken::HEADER_NAME => 'Bearer manual-token'],
        ]);

        Http::fake([
            'https://api.example.com/secure' => Http::response(['ok' => true], 200),
        ]);

        $this->post(route('addresses.check', [$site, $address]));

        Http::assertSent(fn ($request) => $request->hasHeader(SiteToken::HEADER_NAME, 'Bearer manual-token'));
    }

    public function test_removing_a_token_detaches_it_from_addresses(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $token = SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'detach-me',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/secure',
            'site_token_id' => $token->id,
        ]);

        Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->set('tokens', [
                ['id' => null, 'name' => '', 'value' => ''],
            ])
            ->call('save')
            ->assertRedirect(route('sites.show', $site));

        $this->assertSame(0, $site->tokens()->count());
        $this->assertNull($address->fresh()->site_token_id);
    }
}
