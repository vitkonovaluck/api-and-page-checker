<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['site_id', 'name', 'endpoint', 'http_method', 'schedule_enabled', 'request_headers', 'request_body', 'last_checked_at'])]
class Address extends Model
{
    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const METHODS_WITH_BODY = ['POST', 'PUT', 'PATCH'];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'schedule_enabled' => 'boolean',
            'request_headers' => 'array',
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

    public function previousSnapshot(): HasOne
    {
        return $this->hasOne(Snapshot::class)->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->where(
                    'id',
                    '<',
                    Snapshot::query()
                        ->selectRaw('max(inner_snapshots.id)')
                        ->from('snapshots as inner_snapshots')
                        ->whereColumn('inner_snapshots.address_id', 'snapshots.address_id')
                );
            }
        );
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

    public function supportsRequestBody(): bool
    {
        return in_array(strtoupper((string) $this->http_method), self::METHODS_WITH_BODY, true);
    }
}
