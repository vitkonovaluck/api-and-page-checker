<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_protected_pages(): void
    {
        $this->get(route('sites.index'))->assertRedirect(route('login'));
        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_cannot_visit_login_or_register(): void
    {
        $this->actingAsUser();

        $this->get(route('login'))->assertRedirect(route('sites.index'));
        $this->get(route('register'))->assertRedirect(route('sites.index'));
    }

    public function test_login_and_register_routes_show_the_landing_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('landing.hero_title'))
            ->assertSeeLivewire(Login::class);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee(__('landing.hero_title'))
            ->assertSeeLivewire(Register::class);
    }

    public function test_login_modal_opens_and_closes(): void
    {
        Livewire::test(Login::class)
            ->assertSet('show', false)
            ->dispatch('open-login')
            ->assertSet('show', true)
            ->call('close')
            ->assertSet('show', false);
    }

    public function test_opening_register_closes_the_login_modal(): void
    {
        Livewire::test(Login::class, ['show' => true])
            ->dispatch('open-register')
            ->assertSet('show', false);
    }

    public function test_opening_login_closes_the_register_modal(): void
    {
        Livewire::test(Register::class, ['show' => true])
            ->dispatch('open-login')
            ->assertSet('show', false);
    }

    public function test_registration_assigns_the_default_free_plan(): void
    {
        $plan = Plan::factory()->default()->create();

        Livewire::test(Register::class)
            ->set('name', 'New User')
            ->set('email', 'new-user@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertRedirect(route('sites.index'));

        $user = User::query()->where('email', 'new-user@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->plan->is($plan));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_log_in_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'member@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('sites.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_credentials_show_an_error(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'member@example.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_social_only_users_cannot_log_in_with_password(): void
    {
        User::factory()->socialOnly()->create([
            'email' => 'social@example.com',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'social@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }
}
