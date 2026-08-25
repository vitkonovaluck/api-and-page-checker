<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\ImportAgentAddressesAction;
use App\DTOs\ImportAgentAddressesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\ImportAgentAddressesRequest;
use App\Http\Resources\Api\V1\Agent\AgentImportedAddressesResource;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class AgentAddressController extends Controller
{
    public function store(
        ImportAgentAddressesRequest $request,
        Site $site,
        ImportAgentAddressesAction $import,
    ): JsonResponse {
        $this->authorize('update', $site);

        $user = $request->user();
        assert($user instanceof User);

        $result = $import->execute(
            $user,
            $site,
            ImportAgentAddressesDTO::fromRequest($request),
        );

        $status = $result->created > 0 ? 201 : 200;

        return (new AgentImportedAddressesResource($result))
            ->response()
            ->setStatusCode($status);
    }
}
