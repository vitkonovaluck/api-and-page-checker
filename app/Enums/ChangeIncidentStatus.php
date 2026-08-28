<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangeIncidentStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
}
