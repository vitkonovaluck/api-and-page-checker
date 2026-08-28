<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

final class EnsurePersonalOrganizationAction
{
    public function execute(User $user): Organization
    {
        $existing = Organization::query()
            ->where('owner_user_id', $user->id)
            ->where('is_personal', true)
            ->first();

        if ($existing !== null) {
            $this->ensureMembership($existing, $user);

            return $existing;
        }

        $organization = Organization::query()->create([
            'owner_user_id' => $user->id,
            'name' => $user->name,
            'is_personal' => true,
        ]);

        $this->ensureMembership($organization, $user);

        return $organization;
    }

    private function ensureMembership(Organization $organization, User $user): void
    {
        $organization->memberships()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => OrganizationRole::Owner],
        );
    }
}
