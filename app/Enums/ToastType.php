<?php

declare(strict_types=1);

namespace App\Enums;

enum ToastType: string
{
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    /**
     * @return array<string, self>
     */
    public static function sessionMap(): array
    {
        return [
            'success' => self::Success,
            'status' => self::Success,
            'error' => self::Error,
            'warning' => self::Warning,
            'info' => self::Info,
        ];
    }

    public function containerClass(): string
    {
        return match ($this) {
            self::Success => 'border border-emerald-400/20 border-l-4 border-l-emerald-400 bg-emerald-400/10 text-emerald-100',
            self::Error => 'border border-rose-400/20 border-l-4 border-l-rose-400 bg-rose-400/10 text-rose-100',
            self::Warning => 'border border-amber-300/20 border-l-4 border-l-amber-300 bg-amber-300/10 text-amber-100',
            self::Info => 'border border-cyan-400/20 border-l-4 border-l-cyan-400 bg-cyan-400/10 text-cyan-100',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function containerClasses(): array
    {
        $classes = [];

        foreach (self::cases() as $type) {
            $classes[$type->value] = $type->containerClass();
        }

        return $classes;
    }
}
