<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

#[Fillable([
    'name',
    'base_url',
    'schedule_enabled',
    'schedule_interval',
    'schedule_last_run_at',
    'requests_per_minute',
])]
class Site extends Model
{
    public const CHECKS_PER_MINUTE_MIN = 1;

    public const CHECKS_PER_MINUTE_MAX = 120;

    public const CHECKS_PER_MINUTE_DEFAULT = 32;

    public const SCHEDULE_INTERVAL_AFTER = 'after';

    public const SCHEDULE_INTERVALS = [
        self::SCHEDULE_INTERVAL_AFTER => 0,
        '5m' => 5,
        '15m' => 15,
        '30m' => 30,
        '1h' => 60,
        '6h' => 360,
        '1d' => 1440,
    ];

    public const SCHEDULE_INTERVAL_LABELS = [
        self::SCHEDULE_INTERVAL_AFTER => 'Після попередньої перевірки (1 хв)',
        '5m' => 'Кожні 5 хвилин',
        '15m' => 'Кожні 15 хвилин',
        '30m' => 'Кожні 30 хвилин',
        '1h' => 'Щогодини',
        '6h' => 'Кожні 6 годин',
        '1d' => 'Щодня',
    ];

    protected function casts(): array
    {
        return [
            'schedule_enabled' => 'boolean',
            'schedule_last_run_at' => 'datetime',
        ];
    }

    public function checksPerMinute(): int
    {
        if ($this->requests_per_minute !== null) {
            return max(0, (int) $this->requests_per_minute);
        }

        return (int) config('checking.requests_per_minute', self::CHECKS_PER_MINUTE_DEFAULT);
    }

    public function usesChainChecks(): bool
    {
        return $this->schedule_enabled && $this->schedule_interval === self::SCHEDULE_INTERVAL_AFTER;
    }

    public static function checkQueueName(int $siteId): string
    {
        $prefix = (string) config('checking.queue_prefix', 'site');

        return $prefix.'-'.$siteId;
    }

    public function checkQueue(): string
    {
        return self::checkQueueName((int) $this->id);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function checkRuns(): HasMany
    {
        return $this->hasMany(CheckRun::class);
    }

    public function snapshots(): HasManyThrough
    {
        return $this->hasManyThrough(Snapshot::class, Address::class);
    }

    public function lastCheckedAt(): ?CarbonInterface
    {
        $timestamp = $this->addresses()
            ->whereNotNull('last_checked_at')
            ->max('last_checked_at');

        return $timestamp ? Carbon::parse($timestamp) : null;
    }

    public function scheduleIntervalMinutes(): ?int
    {
        if ($this->schedule_interval === null) {
            return null;
        }

        return self::SCHEDULE_INTERVALS[$this->schedule_interval] ?? null;
    }

    /**
     * Start of the current clock-aligned schedule slot (e.g. :00/:15/:30/:45 for 15m).
     */
    public function currentScheduleSlotStart(?CarbonInterface $now = null): ?CarbonInterface
    {
        $minutes = $this->scheduleIntervalMinutes();
        if ($minutes === null || $minutes < 1) {
            return null;
        }

        $now ??= now();
        $cursor = $now->copy()->second(0)->microsecond(0);

        if ($minutes >= 1440) {
            return $cursor->startOfDay();
        }

        if ($minutes >= 60 && $minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);
            $alignedHour = intdiv($cursor->hour, $hours) * $hours;

            return $cursor->copy()->startOfDay()->addHours($alignedHour);
        }

        $minutesFromMidnight = ($cursor->hour * 60) + $cursor->minute;
        $alignedMinutes = intdiv($minutesFromMidnight, $minutes) * $minutes;

        return $cursor->copy()->startOfDay()->addMinutes($alignedMinutes);
    }

    public function isAtScheduleSlotStart(?CarbonInterface $now = null, ?CarbonInterface $slotStart = null): bool
    {
        $now ??= now();
        $slotStart ??= $this->currentScheduleSlotStart($now);

        if ($slotStart === null) {
            return false;
        }

        return $now->copy()->second(0)->microsecond(0)->equalTo($slotStart);
    }

    public function isDueForScheduledCheck(?CarbonInterface $now = null): bool
    {
        if (! $this->schedule_enabled) {
            return false;
        }

        $minutes = $this->scheduleIntervalMinutes();
        if ($minutes === null) {
            return false;
        }

        $now ??= now();
        $slotStart = $this->currentScheduleSlotStart($now);
        if ($slotStart === null) {
            return false;
        }

        // First run waits for a clock boundary so load stays aligned.
        if ($this->schedule_last_run_at === null) {
            return $this->isAtScheduleSlotStart($now, $slotStart);
        }

        // Due once we enter a new slot (including catch-up after downtime).
        return $this->schedule_last_run_at->lt($slotStart);
    }

    /**
     * Atomically mark the site as started for this schedule tick.
     * Returns false if another process already claimed it (or it is not due).
     */
    public function claimForScheduledCheck(?CarbonInterface $now = null): bool
    {
        if (! $this->schedule_enabled) {
            return false;
        }

        $minutes = $this->scheduleIntervalMinutes();
        if ($minutes === null) {
            return false;
        }

        $now ??= now();
        $slotStart = $this->currentScheduleSlotStart($now);
        if ($slotStart === null) {
            return false;
        }

        $claimedAt = $now->copy();
        $atSlotStart = $this->isAtScheduleSlotStart($now, $slotStart);

        $affected = static::query()
            ->whereKey($this->id)
            ->where('schedule_enabled', true)
            ->where(function ($query) use ($slotStart, $atSlotStart) {
                $query->where('schedule_last_run_at', '<', $slotStart);

                // Never-run sites only claim on an aligned minute.
                if ($atSlotStart) {
                    $query->orWhereNull('schedule_last_run_at');
                }
            })
            ->update(['schedule_last_run_at' => $claimedAt]);

        if ($affected > 0) {
            $this->forceFill(['schedule_last_run_at' => $claimedAt]);
        }

        return $affected > 0;
    }
}
