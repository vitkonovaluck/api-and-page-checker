<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\DiffOptionsDTO;
use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\DiffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function destroy(Site $site, Address $address): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);
        $this->authorize('update', $site);

        $address->delete();

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Адресу видалено.');
    }

    public function showSnapshot(
        Request $request,
        Site $site,
        Address $address,
        Snapshot $snapshot,
        DiffService $diffService,
    ): View {
        abort_unless($address->site_id === $site->id, 404);
        abort_unless($snapshot->address_id === $address->id, 404);
        $this->authorize('view', $site);

        $address->setRelation('site', $site);
        $compareId = $request->integer('compare');
        $previous = $compareId > 0
            ? Snapshot::query()
                ->where('address_id', $address->id)
                ->whereKey($compareId)
                ->first()
            : $snapshot->previous();
        $diff = $diffService->compare($previous, $snapshot, DiffOptionsDTO::fromAddress($address));

        $compareSnapshots = Snapshot::query()
            ->where('address_id', $address->id)
            ->where('id', '!=', $snapshot->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'created_at']);

        return view('addresses.snapshot', [
            'site' => $site,
            'address' => $address,
            'snapshot' => $snapshot,
            'previous' => $previous,
            'diff' => $diff,
            'compareSnapshots' => $compareSnapshots,
        ]);
    }

    public function destroySnapshot(Site $site, Address $address, Snapshot $snapshot): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);
        abort_unless($snapshot->address_id === $address->id, 404);
        $this->authorize('update', $site);

        $snapshot->delete();

        return redirect()
            ->route('addresses.show', [$site, $address])
            ->with('success', 'Знімок видалено.');
    }
}
