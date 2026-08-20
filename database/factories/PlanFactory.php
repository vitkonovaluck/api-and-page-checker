<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'max_sites' => (int) config('plans.default_max_sites', 3),
            'max_addresses_per_site' => (int) config('plans.default_max_addresses_per_site', 20),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => (string) config('plans.default_name', 'Free'),
            'slug' => (string) config('plans.default_slug', 'free'),
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
