<?php

declare(strict_types=1);

namespace App\Enums;

enum DiffClassification: string
{
    case None = 'none';
    case SchemaChange = 'schema_change';
    case ValueChange = 'value_change';
    case Mixed = 'mixed';
}
