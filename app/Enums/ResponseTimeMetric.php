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

    public function chartTitle(): string
    {
        return match ($this) {
            self::Total => 'Історія часу відповіді',
            self::Ttfb => 'Історія TTFB',
        };
    }

    public function chartSiteTitle(): string
    {
        return match ($this) {
            self::Total => 'Історія часу відповіді адрес',
            self::Ttfb => 'Історія TTFB адрес',
        };
    }

    public function chartSiteSeriesLabel(): string
    {
        return match ($this) {
            self::Total => 'Середнє по всіх адресах',
            self::Ttfb => 'Середнє TTFB по всіх адресах',
        };
    }

    public function chartSiteDescription(): string
    {
        return match ($this) {
            self::Total => 'Середнє значення часу відповіді по всіх адресах за обраний період',
            self::Ttfb => 'Середнє значення TTFB по всіх адресах за обраний період',
        };
    }

    public function chartAddressDescription(): string
    {
        return match ($this) {
            self::Total => 'Час відповіді адреси за обраний період',
            self::Ttfb => 'TTFB адреси за обраний період',
        };
    }
}
