<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final class IssueApiTokenAction
{
    /**
     * @return array{plainTextToken: string, name: string}
     */
    public function execute(User $user, string $name): array
    {
        $token = $user->createToken($name, ['api']);

        return [
            'plainTextToken' => $token->plainTextToken,
            'name' => $name,
        ];
    }
}
