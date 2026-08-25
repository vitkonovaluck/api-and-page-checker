<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'max_sites',
    'max_addresses_per_site',
    'max_addresses_total',
    'price_monthly',
    'sort_order',
    'is_default',
    'is_active',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'price_monthly' => 0,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Plan $plan): void {
            if ($plan->slug === null || $plan->slug === '') {
                $plan->slug = Str::slug($plan->name) ?: 'plan';
            }

            if (! $plan->is_default) {
                return;
            }

            static::query()
                ->when($plan->exists, fn (Builder $query) => $query->whereKeyNot($plan->id))
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isDeletable(): bool
    {
        return ! $this->is_default && ! $this->users()->exists();
    }

    public function hasUnlimitedSites(): bool
    {
        return $this->max_sites === null;
    }

    public function isFree(): bool
    {
        return (int) $this->price_monthly === 0;
    }

    /**
     * @return list<string>
     */
    protected function limitLines(): Attribute
    {
        return Attribute::get(function (): array {
            $lines = [];

            $lines[] = $this->hasUnlimitedSites()
                ? __('landing.pricing_sites_unlimited')
                : __('landing.pricing_sites', ['count' => $this->max_sites]);

            if ($this->max_addresses_per_site !== null) {
                $lines[] = __('landing.pricing_addresses', ['count' => $this->max_addresses_per_site]);
            }

            if ($this->max_addresses_total !== null) {
                $lines[] = __('landing.pricing_addresses_total', ['count' => $this->max_addresses_total]);
            }

            return $lines;
        });
    }

    protected function priceLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->isFree()) {
                return __('landing.pricing_free');
            }

            return __('landing.pricing_price', ['amount' => $this->price_monthly]);
        });
    }
}
