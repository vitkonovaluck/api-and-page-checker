<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Addresses\CreateAddressModal;
use App\Livewire\Sites\CreateSiteModal;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_more_sites_than_the_plan_allows(): void
    {
        $plan = Plan::factory()->create([
            'max_sites' => 3,
            'max_addresses_per_site' => 20,
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id]);
        $this->actingAs($user);

        Site::factory()->count(3)->create(['user_id' => $user->id]);

        Livewire::test(CreateSiteModal::class)
            ->set('name', 'Fourth')
            ->set('base_url', 'https://fourth.example.com')
            ->call('save')
            ->assertHasErrors(['name']);

        $this->assertSame(3, $user->sites()->count());
    }

    public function test_user_cannot_create_more_addresses_than_the_plan_allows(): void
    {
        $plan = Plan::factory()->create([
            'max_sites' => 3,
            'max_addresses_per_site' => 2,
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id]);
        $site = Site::factory()->create(['user_id' => $user->id]);
        $site->addresses()->create(['endpoint' => '/one']);
        $site->addresses()->create(['endpoint' => '/two']);
        $this->actingAs($user);

        Livewire::test(CreateAddressModal::class, ['site' => $site])
            ->set('endpoints', "/three\n")
            ->call('save')
            ->assertHasErrors(['endpoints']);

        $this->assertSame(2, $site->addresses()->count());
    }
}
