<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ColorScheme;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'plan_id', 'role', 'color_scheme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::User->value,
        'color_scheme' => ColorScheme::DarkCyan->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'color_scheme' => ColorScheme::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function usesPasswordLogin(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return Builder<Site>
     */
    public function accessibleSites(): Builder
    {
        $organizationIds = $this->organizations()->pluck('organizations.id');

        return Site::query()->where(function (Builder $query) use ($organizationIds): void {
            $query->where('user_id', $this->id)
                ->orWhereIn('organization_id', $organizationIds);
        });
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_user_id');
    }

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    public function personalOrganization(): ?Organization
    {
        return $this->ownedOrganizations()->where('is_personal', true)->first();
    }

    public function addresses(): HasManyThrough
    {
        return $this->hasManyThrough(Address::class, Site::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function checkAgents(): HasMany
    {
        return $this->hasMany(CheckAgent::class);
    }
}
