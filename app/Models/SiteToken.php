<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['site_id', 'name', 'value'])]
class SiteToken extends Model
{
    /** @use HasFactory<SiteTokenFactory> */
    use HasFactory;

    public const HEADER_NAME = 'Authorization';

    public const VALUE_PREFIX = 'Bearer';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function authorizationHeaderValue(): string
    {
        return self::VALUE_PREFIX.' '.$this->value;
    }
}
