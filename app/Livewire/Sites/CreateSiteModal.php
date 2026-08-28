<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Actions\EnsurePersonalOrganizationAction;
use App\Livewire\Concerns\NormalizesEndpoint;
use App\Models\Site;
use App\Models\User;
use App\Services\PlanQuota;
use Illuminate\Support\Facades\Auth;
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

    public function save(PlanQuota $quota, EnsurePersonalOrganizationAction $ensureOrganization): void
    {
        $this->authorize('create', Site::class);

        $validated = $this->validate();
        $user = $this->currentUser();

        $quota->assertCanCreateSite($user);
        $organization = $ensureOrganization->execute($user);

        $site = $user->sites()->create([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'organization_id' => $organization->id,
        ]);

        if (! empty($validated['endpoint'])) {
            $quota->assertCanCreateAddresses($user, $site, 1);
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

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
