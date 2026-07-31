<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Site;
use App\Services\SnapshotChecker;
use Illuminate\Http\RedirectResponse;

class CheckController extends Controller
{
    public function store(Site $site, Address $address, SnapshotChecker $checker): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);

        $result = $checker->check($address);
        $diff = $result['diff'];

        $message = $diff['is_first']
            ? 'Перший знімок збережено.'
            : ($diff['has_changes']
                ? 'Перевірку виконано. Виявлено зміни.'
                : 'Перевірку виконано. Змін немає.');

        return redirect()
            ->route('addresses.show', [$site, $address])
            ->with('success', $message)
            ->with('diff_highlight', true);
    }

    public function storeAll(Site $site, SnapshotChecker $checker): RedirectResponse
    {
        $addresses = $site->addresses()->orderBy('id')->get();

        if ($addresses->isEmpty()) {
            return redirect()
                ->route('sites.show', $site)
                ->with('success', 'Немає адрес для перевірки.');
        }

        $checked = 0;
        $withChanges = 0;
        $withErrors = 0;

        foreach ($addresses as $address) {
            $result = $checker->check($address);
            $checked++;

            if ($result['snapshot']->error_message) {
                $withErrors++;
            }

            if (! empty($result['diff']['has_changes']) && empty($result['diff']['is_first'])) {
                $withChanges++;
            }
        }

        return redirect()
            ->route('sites.show', $site)
            ->with(
                'success',
                "Перевірено {$checked} адрес: {$withChanges} зі змінами, {$withErrors} з помилками."
            );
    }
}
