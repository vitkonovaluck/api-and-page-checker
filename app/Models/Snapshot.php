<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'address_id',
    'check_run_id',
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

    public function previous(): ?self
    {
        return static::query()
            ->where('address_id', $this->address_id)
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();
    }
}
