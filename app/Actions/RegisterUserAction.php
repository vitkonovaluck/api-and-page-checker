<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

final class RegisterUserAction
{
    public function execute(string $name, string $email, ?string $password = null): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::User,
            'plan_id' => $this->defaultPlan()->id,
        ]);
    }

    private function defaultPlan(): Plan
    {
        return Plan::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
