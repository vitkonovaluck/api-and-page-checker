<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertChannel: string
{
    case Mail = 'mail';
    case Webhook = 'webhook';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::Mail => __('alerts.channels.mail'),
            self::Webhook => __('alerts.channels.webhook'),
            self::Telegram => __('alerts.channels.telegram'),
        };
    }
}
