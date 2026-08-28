<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AddressKind;
use App\Models\Address;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->optional()->words(2, true),
            'endpoint' => '/'.fake()->unique()->slug(),
            'http_method' => 'GET',
            'schedule_enabled' => true,
            'kind' => AddressKind::Http,
        ];
    }
}
