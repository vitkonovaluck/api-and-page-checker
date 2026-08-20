<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ToastType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_uses_landing_dark_theme(): void
    {
        $this->actingAsUser();

        $this->get(route('sites.index'))
            ->assertOk()
            ->assertSee('bg-zinc-950', false)
            ->assertSee('bg-cyan-400', false)
            ->assertSee('landing-grid', false)
            ->assertSee(__('landing.brand'));
    }

    public function test_settings_page_uses_landing_dark_cards(): void
    {
        $this->actingAsUser();

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('bg-zinc-900/80', false)
            ->assertSee('border-white/10', false);
    }

    public function test_toast_types_use_landing_status_palette(): void
    {
        $this->assertStringContainsString('bg-emerald-400/10', ToastType::Success->containerClass());
        $this->assertStringContainsString('bg-rose-400/10', ToastType::Error->containerClass());
        $this->assertStringContainsString('bg-amber-300/10', ToastType::Warning->containerClass());
        $this->assertStringContainsString('bg-cyan-400/10', ToastType::Info->containerClass());
    }
}
