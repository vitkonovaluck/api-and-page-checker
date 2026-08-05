<?php

namespace App\Livewire\Sites;

use App\Livewire\Concerns\NormalizesEndpoint;
use App\Models\Site;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateSiteModal extends Component
{
    use NormalizesEndpoint;

    public bool $show = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|url|max:2048')]
    public string $base_url = 'http://localhost:8000';

    #[Validate('nullable|string|max:255')]
    public string $address_name = '';

    #[Validate('nullable|string|max:2048')]
    public string $endpoint = '';

    #[On('open-create-site')]
    public function open(): void
    {
        $this->resetValidation();
        $this->reset(['name', 'address_name', 'endpoint']);
        $this->base_url = 'http://localhost:8000';
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate();

        $site = Site::query()->create([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
        ]);

        if (! empty($validated['endpoint'])) {
            $site->addresses()->create([
                'name' => $validated['address_name'] ?: null,
                'endpoint' => $this->normalizeEndpoint($validated['endpoint']),
            ]);
        }

        $this->show = false;
        session()->flash('success', 'Сайт створено.');
        $this->redirect(route('sites.show', $site), navigate: true);
    }

    public function render()
    {
        return view('livewire.sites.create-site-modal');
    }
}
