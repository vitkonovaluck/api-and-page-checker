<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_home_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('landing.hero_title'));
    }

    public function test_authenticated_users_can_view_the_sites_index(): void
    {
        $this->actingAsUser();

        $this->get(route('sites.index'))
            ->assertOk();
    }
}
