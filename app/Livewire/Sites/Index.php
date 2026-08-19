<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Services\CheckingGuard;
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
        $site->delete();

        session()->flash('success', 'Сайт видалено.');
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function refreshData(CheckingGuard $guard): void
    {
        $this->syncBusyState($guard);
    }

    public function render(CheckingGuard $guard): View
    {
        $this->syncBusyState($guard);

        $sites = Site::query()
            ->withCount('addresses')
            ->with(['addresses' => fn ($q) => $q->orderByDesc('last_checked_at')])
            ->orderByDesc('updated_at')
            ->get();

        return view('livewire.sites.index', compact('sites'));
    }

    public function checksBusy(): bool
    {
        return $this->checksBusy;
    }

    private function syncBusyState(CheckingGuard $guard): void
    {
        $this->busySiteIds = $guard->busySiteIds();
        $this->checksBusy = $this->busySiteIds !== [];
    }
}
