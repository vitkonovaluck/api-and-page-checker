<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'site_id',
    'source',
    'started_at',
])]
class CheckRun extends Model
{
    public const UPDATED_AT = null;

    public const SOURCE_SCHEDULE = 'schedule';

    public const SOURCE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'created_at' => 'datetime',
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

    public static function start(Site $site, string $source): self
    {
        return static::query()->create([
            'site_id' => $site->id,
            'source' => $source,
            'started_at' => now(),
        ]);
    }
}
