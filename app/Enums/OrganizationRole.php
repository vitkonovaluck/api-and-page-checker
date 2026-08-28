<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function canUpdate(): bool
    {
        return $this === self::Owner || $this === self::Operator;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }
}
