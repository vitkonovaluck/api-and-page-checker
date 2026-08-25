<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AgentExtensionLoginResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  array{status: string, token: ?string, message: ?string}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{status: string, token: ?string, message: ?string} $payload */
        $payload = $this->resource;

        return [
            'status' => $payload['status'],
            'token' => $payload['token'],
            'message' => $payload['message'],
        ];
    }
}
