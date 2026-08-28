<?php

declare(strict_types=1);

namespace App\Enums;

enum AssertionOperator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case Exists = 'exists';
    case Contains = 'contains';
}
