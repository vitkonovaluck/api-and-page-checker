<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\DiffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function destroy(Site $site, Address $address): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);

        $address->delete();

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Адресу видалено.');
    }

    public function showSnapshot(Site $site, Address $address, Snapshot $snapshot, DiffService $diffService): View
    {
        abort_unless($address->site_id === $site->id, 404);
        abort_unless($snapshot->address_id === $address->id, 404);

        $address->setRelation('site', $site);
        $previous = $snapshot->previous();
        $diff = $diffService->compare($previous, $snapshot);

        return view('addresses.snapshot', [
            'site' => $site,
            'address' => $address,
            'snapshot' => $snapshot,
            'previous' => $previous,
            'diff' => $diff,
        ]);
    }

    public function destroySnapshot(Site $site, Address $address, Snapshot $snapshot): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);
        abort_unless($snapshot->address_id === $address->id, 404);

        $snapshot->delete();

        return redirect()
            ->route('addresses.show', [$site, $address])
            ->with('success', 'Знімок видалено.');
    }
}
