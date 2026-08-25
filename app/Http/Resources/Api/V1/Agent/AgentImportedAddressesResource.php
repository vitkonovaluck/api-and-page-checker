<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\DTOs\ImportAgentAddressesResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AgentImportedAddressesResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private readonly ImportAgentAddressesResultDTO $result)
    {
        parent::__construct($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'created' => $this->result->created,
            'skipped' => $this->result->skipped,
            'addresses' => AgentAddressResource::collection($this->result->addresses)->resolve(),
        ];
    }
}
