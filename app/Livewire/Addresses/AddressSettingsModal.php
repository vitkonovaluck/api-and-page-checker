<?php

namespace App\Livewire\Addresses;

use App\Livewire\Concerns\NormalizesRequestHeaders;
use App\Models\Address;
use App\Models\Site;
use Livewire\Attributes\On;
use Livewire\Component;

class AddressSettingsModal extends Component
{
    use NormalizesRequestHeaders;

    public Site $site;

    public Address $address;

    public bool $show = false;

    /** @var list<array{name: string, value: string}> */
    public array $headers = [['name' => '', 'value' => '']];

    public function mount(Site $site, Address $address): void
    {
        abort_unless($address->site_id === $site->id, 404);

        $this->site = $site;
        $this->address = $address;
        $this->headers = $this->headersToRows($address->request_headers);
    }

    #[On('open-address-settings')]
    public function open(): void
    {
        $this->address->refresh();
        $this->headers = $this->headersToRows($this->address->request_headers);
        $this->resetValidation();
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
        ]);

        $this->address->update([
            'request_headers' => $this->normalizeRequestHeaders($validated['headers'] ?? []),
        ]);

        $this->show = false;
        session()->flash('success', 'Налаштування адреси збережено.');
        $this->redirect(route('addresses.show', [$this->site, $this->address]), navigate: true);
    }

    public function render()
    {
        return view('livewire.addresses.address-settings-modal');
    }
}
