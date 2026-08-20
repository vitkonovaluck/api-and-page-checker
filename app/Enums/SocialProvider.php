<?php

declare(strict_types=1);

namespace App\Enums;

enum SocialProvider: string
{
    case Google = 'google';
    case Github = 'github';
    case Facebook = 'facebook';
    case LinkedIn = 'linkedin';

    public function driver(): string
    {
        return match ($this) {
            self::LinkedIn => 'linkedin-openid',
            default => $this->value,
        };
    }

    public function configKey(): string
    {
        return $this->driver();
    }

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Github => 'GitHub',
            self::Facebook => 'Facebook',
            self::LinkedIn => 'LinkedIn',
        };
    }

    public function isConfigured(): bool
    {
        $clientId = config('services.'.$this->configKey().'.client_id');

        return is_string($clientId) && $clientId !== '';
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return match ($this) {
            self::Github => ['user:email'],
            default => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function configured(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $provider): bool => $provider->isConfigured(),
        ));
    }
}
