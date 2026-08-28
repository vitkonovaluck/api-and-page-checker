<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\EnsurePersonalOrganizationAction;
use App\Enums\ColorScheme;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'plan_id' => Plan::factory(),
            'role' => UserRole::User,
            'color_scheme' => ColorScheme::default(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
        ]);
    }

    public function socialOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'password' => null,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            app(EnsurePersonalOrganizationAction::class)->execute($user);
        });
    }
}
