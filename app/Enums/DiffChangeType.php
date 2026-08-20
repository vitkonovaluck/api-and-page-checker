<?php

declare(strict_types=1);

namespace App\Enums;

enum DiffChangeType: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Changed = 'changed';
    case Reordered = 'reordered';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'додано',
            self::Removed => 'видалено',
            self::Changed => 'змінено',
            self::Reordered => 'пересортовано',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $type) {
            $labels[$type->value] = $type->label();
        }

        return $labels;
    }
}
