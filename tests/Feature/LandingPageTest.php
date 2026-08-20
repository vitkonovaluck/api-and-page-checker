<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_landing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('landing.hero_title'))
            ->assertSee(__('landing.register'))
            ->assertSee(__('landing.features')[0]['title'])
            ->assertSeeLivewire(Login::class)
            ->assertSeeLivewire(Register::class)
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false);
    }

    public function test_authenticated_users_are_redirected_from_the_landing_page_to_sites(): void
    {
        $this->actingAsUser();

        $this->get(route('home'))
            ->assertRedirect(route('sites.index'));
    }

    public function test_landing_page_includes_seo_meta(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('landing.meta_title'), false)
            ->assertSee(__('landing.meta_description', [
                'sites' => config('plans.default_max_sites'),
            ]), false);
    }
}
