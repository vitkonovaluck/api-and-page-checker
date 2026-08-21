<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Actions\StopManualCheckRunAction;
use App\Models\Site;
use App\Models\User;
use App\Services\CheckingGuard;
use App\Services\PlanQuota;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Список сайтів — API Snapshot Checker')]
class Index extends Component
{
    /**
     * @var list<int>
     */
    public array $busySiteIds = [];

    public bool $checksBusy = false;

    public function mount(CheckingGuard $guard): void
    {
        $this->syncBusyState($guard);
    }

    public function delete(Site $site): void
    {
        $this->authorize('delete', $site);

        $site->delete();

        session()->flash('success', 'Сайт видалено.');
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function stopManualCheckRun(int $siteId, StopManualCheckRunAction $action): void
    {
        $site = $this->currentUser()->sites()->whereKey($siteId)->firstOrFail();
        $this->authorize('update', $site);

        if (! $action->queue($site)) {
            session()->flash('error', 'Немає активної ручної перевірки для зупинки.');
            $this->redirect(route('sites.index'), navigate: true);

            return;
        }

        session()->flash('success', 'Перевірку зупинено. Дані цього проходу видаляються.');
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function refreshData(CheckingGuard $guard): void
    {
        $this->syncBusyState($guard);
    }

    public function render(CheckingGuard $guard, PlanQuota $quota, StopManualCheckRunAction $stopManualCheck): View
    {
        $user = $this->currentUser();
        $sites = $user->sites()
            ->withCount('addresses')
            ->with(['addresses' => fn ($q) => $q->orderByDesc('last_checked_at')])
            ->orderByDesc('updated_at')
            ->get();

        $this->syncBusyState($guard, $sites->modelKeys());
        $usage = $quota->siteUsage($user);

        return view('livewire.sites.index', [
            'sites' => $sites,
            'canCreateSite' => $usage['can_create_site'],
            'sitesUsed' => $usage['sites_used'],
            'sitesMax' => $usage['sites_max'],
            'stoppableSiteIds' => $stopManualCheck->stoppableSiteIds($sites->modelKeys()),
        ]);
    }

    public function checksBusy(): bool
    {
        return $this->checksBusy;
    }

    /**
     * @param  list<int|string>  $siteIds
     */
    private function syncBusyState(CheckingGuard $guard, array $siteIds = []): void
    {
        $this->busySiteIds = $guard->busySiteIdsAmong(array_map(intval(...), $siteIds));
        $this->checksBusy = $this->busySiteIds !== [];
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
