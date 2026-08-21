<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\ColorScheme;
use App\Livewire\Settings\ColorSchemePicker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ColorSchemePickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_includes_color_scheme_picker(): void
    {
        $this->actingAsUser();

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSeeLivewire(ColorSchemePicker::class)
            ->assertSee(__('color_scheme.title'));
    }

    public function test_user_can_save_a_color_scheme(): void
    {
        $user = $this->actingAsUser();

        Livewire::test(ColorSchemePicker::class)
            ->call('select', ColorScheme::LightEmerald->value)
            ->assertHasNoErrors()
            ->assertSet('colorScheme', ColorScheme::LightEmerald->value)
            ->assertDispatched('color-scheme-changed');

        $this->assertSame(ColorScheme::LightEmerald, $user->fresh()->color_scheme);
    }

    public function test_saved_color_scheme_is_applied_on_authenticated_pages(): void
    {
        $this->actingAsUser(User::factory()->create([
            'color_scheme' => ColorScheme::LightViolet,
        ]));

        $this->get(route('sites.index'))
            ->assertOk()
            ->assertSee('data-theme="light-violet"', false)
            ->assertSee('class="scroll-smooth"', false);
    }

    public function test_invalid_color_scheme_is_rejected(): void
    {
        $user = $this->actingAsUser();

        Livewire::test(ColorSchemePicker::class)
            ->call('select', 'not-a-scheme')
            ->assertHasErrors('colorScheme');

        $this->assertSame(ColorScheme::default(), $user->fresh()->color_scheme);
    }

    public function test_color_scheme_is_stored_per_user(): void
    {
        $this->actingAsUser();
        $other = User::factory()->create([
            'color_scheme' => ColorScheme::LightRose,
        ]);

        Livewire::test(ColorSchemePicker::class)
            ->call('select', ColorScheme::DarkAmber->value)
            ->assertHasNoErrors();

        $this->assertSame(ColorScheme::LightRose, $other->fresh()->color_scheme);
    }
}
