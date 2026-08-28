<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckAgent>
 */
class CheckAgentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->bothify('Office-##??'),
            'hostname' => fake()->optional()->domainWord(),
            'last_seen_at' => null,
            'last_ip' => null,
            'personal_access_token_id' => null,
            'region' => null,
        ];
    }
}
