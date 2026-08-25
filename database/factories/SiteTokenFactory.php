<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteToken>
 */
class SiteTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->unique()->words(2, true),
            'value' => fake()->sha256(),
        ];
    }
}
