<?php

namespace App\Livewire\Addresses;

use App\Livewire\Concerns\HandlesHttpMethodAndBody;
use App\Livewire\Concerns\NormalizesRequestHeaders;
use App\Models\Address;
use App\Models\Site;
use Livewire\Attributes\On;
use Livewire\Component;

class AddressSettingsModal extends Component
{
    use HandlesHttpMethodAndBody;
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
        $this->loadFromAddress();
    }

    #[On('open-address-settings')]
    public function open(): void
    {
        $this->address->refresh();
        $this->loadFromAddress();
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
        $validated = $this->validate(array_merge([
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
        ], $this->methodAndBodyRules()));

        $this->address->update([
            'http_method' => $validated['http_method'],
            'request_headers' => $this->normalizeRequestHeaders($validated['headers'] ?? []),
            'request_body' => $this->resolvedRequestBody(),
        ]);

        $this->show = false;
        session()->flash('success', 'Налаштування адреси збережено.');
        $this->redirect(route('addresses.show', [$this->site, $this->address]), navigate: true);
    }

    private function loadFromAddress(): void
    {
        $this->http_method = strtoupper((string) ($this->address->http_method ?: 'GET'));
        $this->request_body = (string) ($this->address->request_body ?? '');
        $this->headers = $this->headersToRows($this->address->request_headers);
    }

    public function render()
    {
        return view('livewire.addresses.address-settings-modal');
    }
}
