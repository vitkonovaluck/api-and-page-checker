<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckOutcome: string
{
    case Ok = 'ok';
    case Changed = 'changed';
    case Failed = 'failed';
    case Degraded = 'degraded';
}
