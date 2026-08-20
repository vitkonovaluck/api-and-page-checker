<?php

declare(strict_types=1);

namespace App\Policies;

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
        return $this->owns($user, $site);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Site $site): bool
    {
        return $this->owns($user, $site);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->owns($user, $site);
    }

    private function owns(User $user, Site $site): bool
    {
        return $site->user_id === $user->id;
    }
}
