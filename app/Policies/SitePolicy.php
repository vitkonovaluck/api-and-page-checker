<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Site;
use App\Models\User;

final class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Site $site): bool
    {
        return $this->role($user, $site) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Site $site): bool
    {
        return $this->role($user, $site)?->canUpdate() ?? false;
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->role($user, $site) === OrganizationRole::Owner;
    }

    private function role(User $user, Site $site): ?OrganizationRole
    {
        if ($site->user_id === $user->id) {
            return OrganizationRole::Owner;
        }

        if ($site->organization_id === null) {
            return null;
        }

        $site->loadMissing('organization.memberships');

        return $site->organization?->roleFor($user);
    }
}
