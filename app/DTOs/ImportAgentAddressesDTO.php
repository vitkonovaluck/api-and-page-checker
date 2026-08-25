<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Api\V1\Agent\ImportAgentAddressesRequest;

final readonly class ImportAgentAddressesDTO
{
    /**
     * @param  list<string>  $endpoints
     */
    public function __construct(
        public array $endpoints,
        public bool $scheduleEnabled,
    ) {}

    public static function fromRequest(ImportAgentAddressesRequest $request): self
    {
        /** @var list<string> $endpoints */
        $endpoints = array_values(array_map(
            static fn (mixed $endpoint): string => is_string($endpoint) ? $endpoint : '',
            $request->validated('endpoints'),
        ));

        return new self(
            endpoints: $endpoints,
            scheduleEnabled: $request->boolean('schedule_enabled', true),
        );
    }
}
