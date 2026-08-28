<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class IssueAgentTokenDTO
{
    public function __construct(
        public string $name,
        public ?string $hostname = null,
        public ?string $ip = null,
        public ?string $region = null,
    ) {}
}
