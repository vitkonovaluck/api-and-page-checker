<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ColorScheme;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_access_the_admin_panel(): void
    {
        $this->actingAsUser();

        $this->get('/admincab')
            ->assertForbidden();
    }

    public function test_admins_can_access_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admincab')
            ->assertOk();
    }

    public function test_admins_see_log_hub_under_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admincab')
            ->assertOk()
            ->assertSee(__('admin.navigation.settings'))
            ->assertSee(__('filament-log-hub::log-hub.navigation.entries'));
    }

    public function test_admins_can_browse_log_hub_entries(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admincab/log-hub')
            ->assertOk()
            ->assertSee(__('filament-log-hub::log-hub.navigation.entries'));
    }

    public function test_admins_can_open_log_hub_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admincab/log-hub-settings')
            ->assertOk()
            ->assertSee(__('filament-log-hub::log-hub.navigation.settings'));
    }

    public function test_admin_can_change_a_users_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $free = Plan::factory()->create(['name' => 'Free']);
        $pro = Plan::factory()->create(['name' => 'Pro', 'max_sites' => 10]);
        $member = User::factory()->create(['plan_id' => $free->id]);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $member->id])
            ->fillForm([
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'color_scheme' => $member->color_scheme->value,
                'plan_id' => $pro->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($member->fresh()->plan->is($pro));
    }

    public function test_admin_can_change_a_users_color_scheme(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create([
            'color_scheme' => ColorScheme::DarkCyan,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $member->id])
            ->fillForm([
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'color_scheme' => ColorScheme::LightSky->value,
                'plan_id' => $member->plan_id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ColorScheme::LightSky, $member->fresh()->color_scheme);
    }
}
