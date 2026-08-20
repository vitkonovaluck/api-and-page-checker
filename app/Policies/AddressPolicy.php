<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

final class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return $this->owns($user, $address);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Address $address): bool
    {
        return $this->owns($user, $address);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->owns($user, $address);
    }

    private function owns(User $user, Address $address): bool
    {
        $address->loadMissing('site');

        return $address->site?->user_id === $user->id;
    }
}
