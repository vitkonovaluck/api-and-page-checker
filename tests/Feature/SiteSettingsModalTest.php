<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Sites\SiteSettingsModal;
use App\Models\Address;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_changing_schedule_settings_keeps_modal_open(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        $component = Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->call('open')
            ->set('schedule_enabled', true)
            ->set('schedule_interval', Site::SCHEDULE_INTERVAL_AFTER);

        $component->assertSet('show', true);
    }

    public function test_changing_address_schedule_keeps_modal_open(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
            'schedule_enabled' => false,
        ]);

        $component = Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->call('open')
            ->set('address_schedule', [$address->id]);

        $component->assertSet('show', true);
    }

    public function test_close_action_hides_settings_modal(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        $component = Livewire::test(SiteSettingsModal::class, ['site' => $site])
            ->call('open')
            ->call('close');

        $component->assertSet('show', false);
    }

    public function test_settings_modal_does_not_close_from_native_dialog_events(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);

        $html = Livewire::test(SiteSettingsModal::class, ['site' => $site])->html();

        $this->assertStringContainsString('x-on:cancel.prevent=""', $html);
        $this->assertStringNotContainsString('$wire.close()', $html);
        $this->assertStringNotContainsString('if ($event.target === $el)', $html);
    }
}
