<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Database\Seeders\PlanSeeder;
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
            ->assertSee(__('landing.extension_title'))
            ->assertSee(__('landing.extension_download'))
            ->assertSee(route('extension.chrome'), false)
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

    public function test_landing_page_lists_paid_plans_including_shared_address_pool(): void
    {
        $this->seed(PlanSeeder::class);

        $agency = collect(config('plans.catalog'))->firstWhere('slug', 'agency');
        $this->assertIsArray($agency);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('Pro')
            ->assertSee('Business')
            ->assertSee('Agency')
            ->assertSee(__('landing.pricing_sites_unlimited'))
            ->assertSee(__('landing.pricing_addresses_total', ['count' => $agency['max_addresses_total']]));
    }
}
