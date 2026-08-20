<?php

namespace App\Livewire\Addresses;

use App\Livewire\Concerns\HandlesHttpMethodAndBody;
use App\Livewire\Concerns\NormalizesEndpoint;
use App\Livewire\Concerns\NormalizesRequestHeaders;
use App\Models\Site;
use App\Models\User;
use App\Services\PlanQuota;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateAddressModal extends Component
{
    use HandlesHttpMethodAndBody;
    use NormalizesEndpoint;
    use NormalizesRequestHeaders;

    public Site $site;

    public bool $show = false;

    public string $endpoints = '';

    public string $name = '';

    public bool $schedule_enabled = true;

    /** @var list<array{name: string, value: string}> */
    public array $headers = [['name' => '', 'value' => '']];

    public function mount(Site $site): void
    {
        $this->authorize('update', $site);
        $this->site = $site;
    }

    #[On('open-create-address')]
    public function open(): void
    {
        $this->resetValidation();
        $this->endpoints = '';
        $this->name = '';
        $this->schedule_enabled = true;
        $this->http_method = 'GET';
        $this->request_body = '';
        $this->headers = [['name' => '', 'value' => '']];
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function save(PlanQuota $quota): void
    {
        $this->authorize('update', $this->site);

        $parsed = $this->parseEndpoints($this->endpoints);

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'endpoints' => 'Додайте хоча б один ендпоїнт.',
            ]);
        }

        $validated = $this->validate(array_merge([
            'endpoints' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'schedule_enabled' => ['boolean'],
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
        ], $this->methodAndBodyRules()));

        $headers = $this->normalizeRequestHeaders($validated['headers'] ?? []);
        $body = $this->resolvedRequestBody();
        $name = count($parsed) === 1 ? ($validated['name'] ?: null) : null;

        $quota->assertCanCreateAddresses($this->currentUser(), $this->site, count($parsed));

        DB::transaction(function () use ($parsed, $name, $headers, $body, $validated): void {
            foreach ($parsed as $endpoint) {
                $this->site->addresses()->create([
                    'name' => $name,
                    'endpoint' => $endpoint,
                    'http_method' => $validated['http_method'],
                    'schedule_enabled' => $this->schedule_enabled,
                    'request_headers' => $headers,
                    'request_body' => $body,
                ]);
            }
        });

        $this->show = false;
        $count = count($parsed);
        session()->flash(
            'success',
            $count === 1 ? 'Адресу додано.' : "Додано {$count} адрес.",
        );
        $this->redirect(route('sites.show', $this->site), navigate: true);
    }

    /**
     * @return list<string>
     */
    private function parseEndpoints(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $endpoints = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $endpoint = $this->normalizeEndpoint($line);

            if (in_array($endpoint, $endpoints, true)) {
                throw ValidationException::withMessages([
                    'endpoints' => "Дублікат у списку: {$endpoint}",
                ]);
            }

            if (strlen($endpoint) > 2048) {
                throw ValidationException::withMessages([
                    'endpoints' => "Ендпоїнт занадто довгий: {$endpoint}",
                ]);
            }

            $endpoints[] = $endpoint;
        }

        return $endpoints;
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }

    public function render()
    {
        return view('livewire.addresses.create-address-modal');
    }
}
