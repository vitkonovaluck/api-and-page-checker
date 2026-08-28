<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'site_id',
    'check_agent_id',
    'source',
    'started_at',
    'remaining_jobs',
    'variables',
])]
class CheckRun extends Model
{
    public const UPDATED_AT = null;

    public const SOURCE_SCHEDULE = 'schedule';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CHAIN = 'chain';

    public const SOURCE_AGENT = 'agent';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'created_at' => 'datetime',
            'remaining_jobs' => 'integer',
            'check_agent_id' => 'integer',
            'variables' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function periodicSources(): array
    {
        return [self::SOURCE_SCHEDULE, self::SOURCE_AGENT];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function checkAgent(): BelongsTo
    {
        return $this->belongsTo(CheckAgent::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    public static function start(Site $site, string $source, int $remainingJobs = 0, ?CheckAgent $agent = null): self
    {
        return static::query()->create([
            'site_id' => $site->id,
            'check_agent_id' => $agent?->id,
            'source' => $source,
            'started_at' => now(),
            'remaining_jobs' => max(0, $remainingJobs),
            'variables' => [],
        ]);
    }
}
