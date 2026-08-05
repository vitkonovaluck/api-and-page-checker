<?php

namespace App\Livewire\Addresses;

use App\Livewire\Concerns\NormalizesEndpoint;
use App\Livewire\Concerns\NormalizesRequestHeaders;
use App\Models\Site;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateAddressModal extends Component
{
    use NormalizesEndpoint;
    use NormalizesRequestHeaders;

    public Site $site;

    public bool $show = false;

    public string $endpoint = '';

    public string $name = '';

    public bool $schedule_enabled = true;

    /** @var list<array{name: string, value: string}> */
    public array $headers = [['name' => '', 'value' => '']];

    public function mount(Site $site): void
    {
        $this->site = $site;
    }

    #[On('open-create-address')]
    public function open(): void
    {
        $this->resetValidation();
        $this->endpoint = '';
        $this->name = '';
        $this->schedule_enabled = true;
        $this->headers = [['name' => '', 'value' => '']];
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->endpoint = $this->normalizeEndpoint($this->endpoint);

        $validated = $this->validate([
            'endpoint' => [
                'required',
                'string',
                'max:2048',
                Rule::unique('addresses', 'endpoint')->where(fn ($q) => $q->where('site_id', $this->site->id)),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'schedule_enabled' => ['boolean'],
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
        ], [
            'endpoint.unique' => 'Цей ендпоїнт уже додано до сайту.',
        ]);

        $this->site->addresses()->create([
            'name' => $validated['name'] ?: null,
            'endpoint' => $validated['endpoint'],
            'schedule_enabled' => $this->schedule_enabled,
            'request_headers' => $this->normalizeRequestHeaders($validated['headers'] ?? []),
        ]);

        $this->show = false;
        session()->flash('success', 'Адресу додано.');
        $this->redirect(route('sites.show', $this->site), navigate: true);
    }

    public function render()
    {
        return view('livewire.addresses.create-address-modal');
    }
}
