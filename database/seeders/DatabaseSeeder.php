<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $freePlan = Plan::query()->create([
            'name' => (string) config('plans.default_name'),
            'slug' => (string) config('plans.default_slug'),
            'max_sites' => (int) config('plans.default_max_sites'),
            'max_addresses_per_site' => (int) config('plans.default_max_addresses_per_site'),
            'is_default' => true,
            'is_active' => true,
        ]);

        User::query()->forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'plan_id' => $freePlan->id,
            'role' => UserRole::Admin,
        ]);

        User::query()->forceCreate([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'plan_id' => $freePlan->id,
            'role' => UserRole::User,
        ]);
    }
}
