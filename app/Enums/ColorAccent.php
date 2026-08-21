<?php

declare(strict_types=1);

namespace App\Enums;

enum ColorAccent: string
{
    case Cyan = 'cyan';
    case Emerald = 'emerald';
    case Violet = 'violet';
    case Sky = 'sky';
    case Amber = 'amber';
    case Rose = 'rose';

    public function label(): string
    {
        return __('color_scheme.accents.'.$this->value);
    }

    public function swatchHex(): string
    {
        return match ($this) {
            self::Cyan => '#22d3ee',
            self::Emerald => '#34d399',
            self::Violet => '#a78bfa',
            self::Sky => '#38bdf8',
            self::Amber => '#fbbf24',
            self::Rose => '#fb7185',
        };
    }
}
