<?php

namespace App\Models;

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
])]
class Site extends Model
{
    public const SCHEDULE_INTERVALS = [
        '5m' => 5,
        '15m' => 15,
        '30m' => 30,
        '1h' => 60,
        '6h' => 360,
        '1d' => 1440,
    ];

    public const SCHEDULE_INTERVAL_LABELS = [
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

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function snapshots(): HasManyThrough
    {
        return $this->hasManyThrough(Snapshot::class, Address::class);
    }

    public function lastCheckedAt(): ?Carbon
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

    public function isDueForScheduledCheck(?Carbon $now = null): bool
    {
        if (! $this->schedule_enabled) {
            return false;
        }

        $minutes = $this->scheduleIntervalMinutes();
        if ($minutes === null) {
            return false;
        }

        $now ??= now();

        if ($this->schedule_last_run_at === null) {
            return true;
        }

        return $this->schedule_last_run_at->copy()->addMinutes($minutes)->lte($now);
    }

    /**
     * Atomically mark the site as started for this schedule tick.
     * Returns false if another process already claimed it (or it is not due).
     */
    public function claimForScheduledCheck(?Carbon $now = null): bool
    {
        if (! $this->schedule_enabled) {
            return false;
        }

        $minutes = $this->scheduleIntervalMinutes();
        if ($minutes === null) {
            return false;
        }

        $now ??= now();
        $claimedAt = $now->copy();

        $query = static::query()
            ->whereKey($this->id)
            ->where('schedule_enabled', true);

        if ($this->schedule_last_run_at === null) {
            $affected = $query
                ->whereNull('schedule_last_run_at')
                ->update(['schedule_last_run_at' => $claimedAt]);
        } else {
            $dueAtOrBefore = $now->copy()->subMinutes($minutes);
            $affected = $query
                ->where('schedule_last_run_at', '<=', $dueAtOrBefore)
                ->update(['schedule_last_run_at' => $claimedAt]);
        }

        if ($affected > 0) {
            $this->forceFill(['schedule_last_run_at' => $claimedAt]);
        }

        return $affected > 0;
    }
}
