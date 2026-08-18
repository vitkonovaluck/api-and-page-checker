<?php

declare(strict_types=1);

namespace App\Enums;

enum ResponseTimeMetric: string
{
    case Total = 'total';
    case Ttfb = 'ttfb';

    public const SESSION_KEY = 'response_time_metric';

    public static function normalize(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::tryFrom($value) ?? self::Total;
        }

        return self::Total;
    }

    public function toggleLabel(): string
    {
        return match ($this) {
            self::Total => 'Час відповіді',
            self::Ttfb => 'TTFB',
        };
    }

    public function columnLabel(): string
    {
        return $this->toggleLabel();
    }

    public function averageLabel(): string
    {
        return match ($this) {
            self::Total => 'Сер. час',
            self::Ttfb => 'Сер. TTFB',
        };
    }

    public function scheduleAverageLabel(): string
    {
        return match ($this) {
            self::Total => 'Сер. час (розклад)',
            self::Ttfb => 'Сер. TTFB (розклад)',
        };
    }

    public function latestAverageLabel(): string
    {
        return match ($this) {
            self::Total => 'Сер. час (остання)',
            self::Ttfb => 'Сер. TTFB (остання)',
        };
    }

    public function allAverageLabel(): string
    {
        return match ($this) {
            self::Total => 'Сер. час (усі)',
            self::Ttfb => 'Сер. TTFB (усі)',
        };
    }

    public function historyAverageLabel(): string
    {
        return match ($this) {
            self::Total => 'Середній час',
            self::Ttfb => 'Середній TTFB',
        };
    }

    public function snapshotTimeLabel(): string
    {
        return $this->toggleLabel();
    }

    public static function combinedChartTitle(): string
    {
        return 'Історія часу відповіді та TTFB';
    }

    public static function combinedChartSiteTitle(): string
    {
        return 'Історія часу відповіді та TTFB адрес';
    }

    public static function combinedChartSiteDescription(): string
    {
        return 'Середні значення часу відповіді та TTFB по всіх адресах за обраний період';
    }

    public static function combinedChartAddressDescription(): string
    {
        return 'Час відповіді та TTFB адреси за обраний період';
    }
}
