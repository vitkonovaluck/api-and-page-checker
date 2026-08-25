<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_plan_catalog_with_one_default(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertSame(count(config('plans.catalog')), Plan::query()->count());
        $this->assertSame(1, Plan::query()->where('is_default', true)->count());
        $this->assertTrue(Plan::query()->where('slug', 'free')->where('is_default', true)->exists());

        $agency = collect(config('plans.catalog'))->firstWhere('slug', 'agency');
        $this->assertIsArray($agency);
        $this->assertTrue(
            Plan::query()
                ->where('slug', 'agency')
                ->whereNull('max_sites')
                ->whereNull('max_addresses_per_site')
                ->where('max_addresses_total', $agency['max_addresses_total'])
                ->exists(),
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->assertSame(count(config('plans.catalog')), Plan::query()->count());
        $this->assertSame(1, Plan::query()->where('is_default', true)->count());
    }
}
