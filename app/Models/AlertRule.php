<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertEvent;
use Database\Factories\AlertRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id',
    'address_id',
    'notification_channel_id',
    'events',
    'min_consecutive',
    'cooldown_minutes',
    'notify_on_manual',
    'digest_value_changes',
    'last_sent_at',
])]
class AlertRule extends Model
{
    /** @use HasFactory<AlertRuleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'min_consecutive' => 1,
        'cooldown_minutes' => 0,
        'notify_on_manual' => false,
        'digest_value_changes' => false,
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'min_consecutive' => 'integer',
            'cooldown_minutes' => 'integer',
            'notify_on_manual' => 'boolean',
            'digest_value_changes' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function notificationChannel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }

    public function watches(AlertEvent $event): bool
    {
        foreach ($this->events ?? [] as $watched) {
            $value = $watched instanceof AlertEvent ? $watched->value : (string) $watched;

            if ($value === $event->value) {
                return true;
            }
        }

        return false;
    }

    public function appliesTo(Address $address): bool
    {
        return $this->address_id === null || $this->address_id === $address->id;
    }

    public function isCoolingDown(): bool
    {
        if ($this->cooldown_minutes < 1 || $this->last_sent_at === null) {
            return false;
        }

        return $this->last_sent_at->gt(now()->subMinutes($this->cooldown_minutes));
    }
}
