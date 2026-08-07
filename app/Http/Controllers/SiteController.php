<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    public function copy(Site $site): RedirectResponse
    {
        $site->load('addresses');

        $copy = DB::transaction(function () use ($site) {
            $newSite = Site::query()->create([
                'name' => $site->name.' (копія)',
                'base_url' => $site->base_url,
                'schedule_enabled' => $site->schedule_enabled,
                'schedule_interval' => $site->schedule_interval,
                'schedule_last_run_at' => null,
            ]);

            foreach ($site->addresses as $address) {
                $newSite->addresses()->create([
                    'name' => $address->name,
                    'endpoint' => $address->endpoint,
                    'http_method' => $address->http_method,
                    'schedule_enabled' => $address->schedule_enabled,
                    'request_headers' => $address->request_headers,
                    'request_body' => $address->request_body,
                    'last_checked_at' => null,
                ]);
            }

            return $newSite;
        });

        return redirect()
            ->route('sites.show', $copy)
            ->with('success', 'Сайт скопійовано.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->delete();

        return redirect()
            ->route('sites.index')
            ->with('success', 'Сайт видалено.');
    }
}
