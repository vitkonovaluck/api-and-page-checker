<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\GetSiteBodyChangesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Agent\AgentAddressBodyChangeResource;
use App\Models\Site;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AgentSiteBodyChangeController extends Controller
{
    public function index(Site $site, GetSiteBodyChangesAction $action): AnonymousResourceCollection
    {
        $this->authorize('view', $site);

        return AgentAddressBodyChangeResource::collection($action->execute($site));
    }
}
