<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FindOrCreateFromSocialAction
{
    public function __construct(private RegisterUserAction $registerUser) {}

    public function execute(SocialProvider $provider, string $providerId, ?string $email, ?string $name): User
    {
        if ($email === null || $email === '') {
            throw ValidationException::withMessages([
                'email' => __('auth.social_email_missing'),
            ]);
        }

        $existing = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existing !== null) {
            return $existing->user;
        }

        $user = User::query()->where('email', $email)->first()
            ?? $this->registerUser->execute(
                $name !== null && $name !== '' ? $name : Str::before($email, '@'),
                $email,
            );

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);

        return $user;
    }
}
