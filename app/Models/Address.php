<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AddressKind;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'site_id',
    'name',
    'endpoint',
    'http_method',
    'schedule_enabled',
    'request_headers',
    'request_body',
    'ignore_json_paths',
    'ignore_headers',
    'ignore_body_regex',
    'watch_json_paths',
    'assertions',
    'kind',
    'step_order',
    'extract_json_path',
    'extract_as',
    'last_checked_at',
    'site_token_id',
    'check_agent_id',
    'baseline_snapshot_id',
])]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const METHODS_WITH_BODY = ['POST', 'PUT', 'PATCH'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => AddressKind::Http->value,
        'schedule_enabled' => true,
        'http_method' => 'GET',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'schedule_enabled' => 'boolean',
            'request_headers' => 'array',
            'ignore_json_paths' => 'array',
            'ignore_headers' => 'array',
            'ignore_body_regex' => 'array',
            'watch_json_paths' => 'array',
            'assertions' => 'array',
            'kind' => AddressKind::class,
            'step_order' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function siteToken(): BelongsTo
    {
        return $this->belongsTo(SiteToken::class);
    }

    public function checkAgent(): BelongsTo
    {
        return $this->belongsTo(CheckAgent::class);
    }

    public function baselineSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'baseline_snapshot_id');
    }

    /**
     * @return array<string, string>
     */
    public function resolvedRequestHeaders(): array
    {
        $headers = $this->request_headers ?? [];

        if ($this->siteToken === null) {
            return $headers;
        }

        return array_merge($headers, [
            SiteToken::HEADER_NAME => $this->siteToken->authorizationHeaderValue(),
        ]);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ChangeIncident::class);
    }

    public function openIncident(): HasOne
    {
        return $this->hasOne(ChangeIncident::class)->where('status', 'open')->latestOfMany();
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

    public function isStepped(): bool
    {
        return $this->step_order !== null;
    }
}
