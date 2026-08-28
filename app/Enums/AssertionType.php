<?php

declare(strict_types=1);

namespace App\Enums;

enum AssertionType: string
{
    case StatusIn = 'status_in';
    case MaxResponseMs = 'max_response_ms';
    case MaxTtfbMs = 'max_ttfb_ms';
    case JsonPath = 'json_path';
    case HeaderContains = 'header_contains';
    case BodyContains = 'body_contains';
}
