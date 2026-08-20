<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use App\Services\SiteTransferService;
use Illuminate\Http\RedirectResponse;

final class SiteController extends Controller
{
    public function __construct(private SiteTransferService $transfer) {}

    public function copy(Site $site): RedirectResponse
    {
        $this->authorize('view', $site);

        $copy = $this->transfer->copy($site, $this->currentUser());

        return redirect()
            ->route('sites.show', $copy)
            ->with('success', 'Сайт скопійовано.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()
            ->route('sites.index')
            ->with('success', 'Сайт видалено.');
    }

    private function currentUser(): User
    {
        $user = request()->user();
        assert($user instanceof User);

        return $user;
    }
}
