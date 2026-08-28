<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_user_id',
    'name',
    'is_personal',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_personal' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function roleFor(User $user): ?OrganizationRole
    {
        if ($this->owner_user_id === $user->id) {
            return OrganizationRole::Owner;
        }

        $membership = $this->memberships->firstWhere('user_id', $user->id)
            ?? $this->memberships()->where('user_id', $user->id)->first();

        if ($membership === null) {
            return null;
        }

        return $membership->role instanceof OrganizationRole
            ? $membership->role
            : OrganizationRole::tryFrom((string) $membership->role);
    }
}
