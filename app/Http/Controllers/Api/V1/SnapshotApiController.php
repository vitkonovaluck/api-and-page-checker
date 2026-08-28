<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptBaselineAction;
use App\DTOs\DiffOptionsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShowSnapshotDiffRequest;
use App\Http\Requests\Api\V1\StoreBaselineRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Http\Resources\Api\V1\SnapshotResource;
use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\DiffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SnapshotApiController extends Controller
{
    public function index(Site $site, Address $address): AnonymousResourceCollection
    {
        $this->authorize('view', $site);
        abort_unless($address->site_id === $site->id, 404);

        $snapshots = $address->snapshots()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return SnapshotResource::collection($snapshots);
    }

    public function diff(
        ShowSnapshotDiffRequest $request,
        Site $site,
        Address $address,
        DiffService $diffService,
    ): JsonResponse {
        abort_unless($address->site_id === $site->id, 404);

        $from = Snapshot::query()
            ->where('address_id', $address->id)
            ->whereKey($request->integer('from'))
            ->firstOrFail();
        $to = Snapshot::query()
            ->where('address_id', $address->id)
            ->whereKey($request->integer('to'))
            ->firstOrFail();

        $diff = $diffService->compare($from, $to, DiffOptionsDTO::fromAddress($address));

        return response()->json([
            'from' => $from->id,
            'to' => $to->id,
            'diff' => $diff,
        ]);
    }

    public function baseline(
        StoreBaselineRequest $request,
        Site $site,
        Address $address,
        AcceptBaselineAction $accept,
    ): AddressResource {
        abort_unless($address->site_id === $site->id, 404);

        $snapshot = Snapshot::query()
            ->where('address_id', $address->id)
            ->whereKey($request->integer('snapshot_id'))
            ->firstOrFail();

        $user = $request->user();
        assert($user instanceof User);

        return new AddressResource($accept->execute($address, $snapshot, $user));
    }
}
