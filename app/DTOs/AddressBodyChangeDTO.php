<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Address;
use App\Models\Snapshot;

final readonly class AddressBodyChangeDTO
{
    /**
     * @param  array{
     *     type: string,
     *     changes: list<array{path: string, type: string, old: mixed, new: mixed}>,
     *     text_diff: list<string>
     * }  $body
     */
    public function __construct(
        public Address $address,
        public Snapshot $latest,
        public Snapshot $previous,
        public array $body,
    ) {}
}
