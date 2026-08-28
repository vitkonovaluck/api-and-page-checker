<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertEvent: string
{
    case BodyChanged = 'body_changed';
    case HeadersChanged = 'headers_changed';
    case StatusChanged = 'status_changed';
    case CheckFailed = 'check_failed';
    case SlowResponse = 'slow_response';
    case SchemaChanged = 'schema_changed';
    case ValueChanged = 'value_changed';
    case SslExpiring = 'ssl_expiring';

    public function label(): string
    {
        return match ($this) {
            self::BodyChanged => __('alerts.events.body_changed'),
            self::HeadersChanged => __('alerts.events.headers_changed'),
            self::StatusChanged => __('alerts.events.status_changed'),
            self::CheckFailed => __('alerts.events.check_failed'),
            self::SlowResponse => __('alerts.events.slow_response'),
            self::SchemaChanged => __('alerts.events.schema_changed'),
            self::ValueChanged => __('alerts.events.value_changed'),
            self::SslExpiring => __('alerts.events.ssl_expiring'),
        };
    }
}
