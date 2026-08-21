<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CheckAgent;
use App\Models\User;

final class CheckAgentPolicy
{
    public function view(User $user, CheckAgent $agent): bool
    {
        return $this->owns($user, $agent);
    }

    public function delete(User $user, CheckAgent $agent): bool
    {
        return $this->owns($user, $agent);
    }

    private function owns(User $user, CheckAgent $agent): bool
    {
        return $agent->user_id === $user->id;
    }
}
