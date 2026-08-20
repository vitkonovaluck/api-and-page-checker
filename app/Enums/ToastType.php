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
            self::Success => 'border border-emerald-200 border-l-4 border-l-emerald-500 bg-emerald-50 text-emerald-900',
            self::Error => 'border border-red-200 border-l-4 border-l-red-500 bg-red-50 text-red-900',
            self::Warning => 'border border-amber-200 border-l-4 border-l-amber-500 bg-amber-50 text-amber-900',
            self::Info => 'border border-sky-200 border-l-4 border-l-sky-500 bg-sky-50 text-sky-900',
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
