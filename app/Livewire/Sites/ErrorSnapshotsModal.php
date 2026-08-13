<?php

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Services\CheckStats;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ErrorSnapshotsModal extends Component
{
    use WithPagination;

    public Site $site;

    public bool $show = false;

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    #[On('open-error-snapshots')]
    public function open(): void
    {
        $this->resetPage();
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function render(CheckStats $checkStats)
    {
        $snapshots = $this->show
            ? $checkStats->errorSnapshotsForSite($this->site, scheduledOnly: true)->paginate(15)
            : collect();

        return view('livewire.sites.error-snapshots-modal', [
            'snapshots' => $snapshots,
        ]);
    }
}
