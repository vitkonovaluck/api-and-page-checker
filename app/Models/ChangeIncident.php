<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChangeIncidentStatus;
use Database\Factories\ChangeIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'address_id',
    'opened_snapshot_id',
    'closed_snapshot_id',
    'status',
    'accepted_at',
    'accepted_by',
])]
class ChangeIncident extends Model
{
    /** @use HasFactory<ChangeIncidentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ChangeIncidentStatus::Open->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => ChangeIncidentStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function openedSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'opened_snapshot_id');
    }

    public function closedSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'closed_snapshot_id');
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isOpen(): bool
    {
        return $this->status === ChangeIncidentStatus::Open;
    }
}
