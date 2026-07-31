<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['site_id', 'name', 'endpoint', 'schedule_enabled', 'last_checked_at'])]
class Address extends Model
{
    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'schedule_enabled' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(Snapshot::class)->latestOfMany();
    }

    public function fullUrl(): string
    {
        $base = rtrim((string) $this->site->base_url, '/');
        $endpoint = (string) $this->endpoint;

        if ($endpoint === '' || $endpoint === '/') {
            return $base.'/';
        }

        return $base.'/'.ltrim($endpoint, '/');
    }
}
