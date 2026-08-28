<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\StartPublicCheckRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCheckRunRequest;
use App\Http\Resources\Api\V1\CheckRunResource;
use App\Models\Site;

final class CheckRunApiController extends Controller
{
    public function store(
        StoreCheckRunRequest $request,
        Site $site,
        StartPublicCheckRunAction $start,
    ): CheckRunResource {
        $run = $start->execute($site, $request->addressIds());

        return new CheckRunResource($run);
    }
}
