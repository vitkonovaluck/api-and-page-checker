<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Addresses\CreateAddressModal;
use App\Livewire\Sites\CreateSiteModal;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use App\Services\PlanQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_user_can_create_sites_when_the_plan_has_no_site_limit(): void
    {
        $plan = Plan::factory()->create([
            'max_sites' => null,
            'max_addresses_per_site' => 20,
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id]);
        Site::factory()->count(5)->create(['user_id' => $user->id]);
        $this->actingAs($user);

        Livewire::test(CreateSiteModal::class)
            ->set('name', 'Sixth')
            ->set('base_url', 'https://sixth.example.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(6, $user->sites()->count());
    }

    public function test_user_cannot_exceed_total_address_limit_across_sites(): void
    {
        $plan = Plan::factory()->withTotalAddressLimit(2)->create();
        $user = User::factory()->create(['plan_id' => $plan->id]);
        $first = Site::factory()->create(['user_id' => $user->id]);
        $second = Site::factory()->create(['user_id' => $user->id]);
        $first->addresses()->create(['endpoint' => '/one']);
        $second->addresses()->create(['endpoint' => '/two']);
        $this->actingAs($user);

        Livewire::test(CreateAddressModal::class, ['site' => $second])
            ->set('endpoints', "/three\n")
            ->call('save')
            ->assertHasErrors(['endpoints']);

        $this->assertSame(2, $user->addresses()->count());
    }

    public function test_unlimited_sites_plan_allows_empty_sites_when_address_pool_is_full(): void
    {
        $plan = Plan::factory()->withTotalAddressLimit(1)->create();
        $user = User::factory()->create(['plan_id' => $plan->id]);
        $site = Site::factory()->create(['user_id' => $user->id]);
        $site->addresses()->create(['endpoint' => '/one']);
        $this->actingAs($user);

        Livewire::test(CreateSiteModal::class)
            ->set('name', 'Another')
            ->set('base_url', 'https://another.example.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, $user->sites()->count());
    }

    public function test_import_cannot_exceed_total_address_limit_across_incoming_sites(): void
    {
        $plan = Plan::factory()->withTotalAddressLimit(2)->create();
        $user = User::factory()->create(['plan_id' => $plan->id]);

        try {
            app(PlanQuota::class)->assertCanImport($user, [
                ['addresses' => [['endpoint' => '/a'], ['endpoint' => '/b']]],
                ['addresses' => [['endpoint' => '/c']]],
            ]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }
}
