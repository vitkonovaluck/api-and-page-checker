<?php

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Services\CheckingGuard;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Список сайтів — API Snapshot Checker')]
class Index extends Component
{
    /** @var list<int> */
    public array $busySiteIds = [];

    public function mount(CheckingGuard $guard): void
    {
        $this->busySiteIds = $guard->busySiteIds();
    }

    public function copy(Site $site): void
    {
        $site->load('addresses');

        $copy = DB::transaction(function () use ($site) {
            $newSite = Site::query()->create([
                'name' => $site->name.' (копія)',
                'base_url' => $site->base_url,
                'schedule_enabled' => $site->schedule_enabled,
                'schedule_interval' => $site->schedule_interval,
                'requests_per_minute' => $site->requests_per_minute,
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

        session()->flash('success', 'Сайт скопійовано.');
        $this->redirect(route('sites.show', $copy), navigate: true);
    }

    public function delete(Site $site): void
    {
        $site->delete();

        session()->flash('success', 'Сайт видалено.');
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function refreshData(CheckingGuard $guard): void
    {
        $this->busySiteIds = $guard->busySiteIds();
    }

    public function render(CheckingGuard $guard)
    {
        $this->busySiteIds = $guard->busySiteIds();

        $sites = Site::query()
            ->withCount('addresses')
            ->with(['addresses' => fn ($q) => $q->orderByDesc('last_checked_at')])
            ->orderByDesc('updated_at')
            ->get();

        return view('livewire.sites.index', compact('sites'));
    }
}
