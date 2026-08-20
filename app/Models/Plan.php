<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'max_sites',
    'max_addresses_per_site',
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
    ];

    protected function casts(): array
    {
        return [
            'max_sites' => 'integer',
            'max_addresses_per_site' => 'integer',
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
}
