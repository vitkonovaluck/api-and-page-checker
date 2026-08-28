<?php

declare(strict_types=1);

namespace App\Enums;

enum AddressKind: string
{
    case Http = 'http';
    case OpenApi = 'openapi';
}
