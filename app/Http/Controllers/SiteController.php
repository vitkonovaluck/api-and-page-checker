<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\SiteTransferService;
use Illuminate\Http\RedirectResponse;

final class SiteController extends Controller
{
    public function __construct(private SiteTransferService $transfer) {}

    public function copy(Site $site): RedirectResponse
    {
        $copy = $this->transfer->copy($site);

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
