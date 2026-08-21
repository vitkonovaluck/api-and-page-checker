<?php

declare(strict_types=1);

namespace App\Enums;

enum ColorScheme: string
{
    case DarkCyan = 'dark-cyan';
    case DarkEmerald = 'dark-emerald';
    case DarkViolet = 'dark-violet';
    case DarkSky = 'dark-sky';
    case DarkAmber = 'dark-amber';
    case DarkRose = 'dark-rose';
    case LightCyan = 'light-cyan';
    case LightEmerald = 'light-emerald';
    case LightViolet = 'light-violet';
    case LightSky = 'light-sky';
    case LightAmber = 'light-amber';
    case LightRose = 'light-rose';

    public static function default(): self
    {
        return self::DarkCyan;
    }

    public function isDark(): bool
    {
        return str_starts_with($this->value, 'dark-');
    }

    public function htmlClass(): string
    {
        return $this->isDark() ? 'dark scroll-smooth' : 'scroll-smooth';
    }

    public function accent(): ColorAccent
    {
        [, $accent] = explode('-', $this->value, 2);

        return ColorAccent::from($accent);
    }

    public function label(): string
    {
        return __('color_scheme.schemes.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $scheme) {
            $options[$scheme->value] = $scheme->label();
        }

        return $options;
    }

    public static function fromParts(bool $dark, ColorAccent $accent): self
    {
        $appearance = $dark ? 'dark' : 'light';

        return self::from($appearance.'-'.$accent->value);
    }
}
