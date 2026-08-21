<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResponseTimeMetric;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'address_id',
    'check_run_id',
    'check_agent_id',
    'status_code',
    'headers',
    'body',
    'body_hash',
    'response_time_ms',
    'timing',
    'error_message',
])]
class Snapshot extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'timing' => 'array',
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
            'check_run_id' => 'integer',
            'check_agent_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function checkRun(): BelongsTo
    {
        return $this->belongsTo(CheckRun::class);
    }

    public function checkAgent(): BelongsTo
    {
        return $this->belongsTo(CheckAgent::class);
    }

    public function previous(): ?self
    {
        return static::query()
            ->where('address_id', $this->address_id)
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();
    }

    public function ttfbMs(): ?int
    {
        $value = $this->timing['ttfb_ms'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function timeMs(ResponseTimeMetric $metric): ?int
    {
        return match ($metric) {
            ResponseTimeMetric::Total => $this->response_time_ms,
            ResponseTimeMetric::Ttfb => $this->ttfbMs(),
        };
    }

    public function formattedTimeMs(ResponseTimeMetric $metric): string
    {
        $value = $this->timeMs($metric);

        return $value === null ? '—' : $value.' ms';
    }

    public function timeDeltaMs(?self $previous, ResponseTimeMetric $metric): ?int
    {
        if ($previous === null) {
            return null;
        }

        $current = $this->timeMs($metric);
        $old = $previous->timeMs($metric);

        if ($current === null || $old === null) {
            return null;
        }

        return $current - $old;
    }
}
