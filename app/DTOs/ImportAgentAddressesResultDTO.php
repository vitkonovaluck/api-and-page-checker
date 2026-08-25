<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Address;
use Illuminate\Support\Collection;

final readonly class ImportAgentAddressesResultDTO
{
    /**
     * @param  Collection<int, Address>  $addresses
     */
    public function __construct(
        public int $created,
        public int $skipped,
        public Collection $addresses,
    ) {}
}
