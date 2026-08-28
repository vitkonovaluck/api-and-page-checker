<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Snapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SnapshotRecorded
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $diff
     */
    public function __construct(
        public Snapshot $snapshot,
        public array $diff,
        public string $source,
    ) {}
}
