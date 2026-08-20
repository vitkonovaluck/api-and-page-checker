<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fn (): mixed => auth()->id() ?? User::factory(),
            'name' => fake()->unique()->company(),
            'base_url' => 'https://'.fake()->unique()->domainName(),
            'schedule_enabled' => false,
            'schedule_interval' => null,
            'schedule_last_run_at' => null,
            'requests_per_minute' => null,
        ];
    }
}
