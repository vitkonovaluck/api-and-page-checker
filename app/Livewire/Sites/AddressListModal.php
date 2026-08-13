<?php

namespace App\Livewire\Sites;

use App\Models\Site;
use Livewire\Attributes\On;
use Livewire\Component;

class AddressListModal extends Component
{
    public Site $site;

    public bool $show = false;

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    #[On('open-address-list')]
    public function open(): void
    {
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function render()
    {
        $addresses = $this->show
            ? $this->site->addresses()->orderBy('id')->get()
            : collect();

        return view('livewire.sites.address-list-modal', [
            'addresses' => $addresses,
        ]);
    }
}
