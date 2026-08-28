<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

final class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $organization->roleFor($user) !== null;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $organization->roleFor($user)?->canManageMembers() ?? false;
    }
}
