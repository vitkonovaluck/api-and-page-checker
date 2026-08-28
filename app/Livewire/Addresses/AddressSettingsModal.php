<?php

declare(strict_types=1);

namespace App\Livewire\Addresses;

use App\Enums\AddressKind;
use App\Livewire\Concerns\HandlesHttpMethodAndBody;
use App\Livewire\Concerns\NormalizesRequestHeaders;
use App\Models\Address;
use App\Models\CheckAgent;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
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

    public mixed $siteTokenId = null;

    public mixed $checkAgentId = null;

    public string $kind = AddressKind::Http->value;

    public string $ignoreJsonPaths = '';

    public string $ignoreHeaders = '';

    public string $ignoreBodyRegex = '';

    public string $watchJsonPaths = '';

    public string $assertionsJson = '';

    public mixed $stepOrder = null;

    public string $extractJsonPath = '';

    public string $extractAs = '';

    public function mount(Site $site, Address $address): void
    {
        abort_unless($address->site_id === $site->id, 404);
        $this->authorize('view', $site);

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
        $this->authorize('update', $this->site);

        $validated = $this->validate(array_merge([
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
            'siteTokenId' => [
                'nullable',
                'integer',
                Rule::exists('site_tokens', 'id')->where('site_id', $this->site->id),
            ],
            'checkAgentId' => [
                'nullable',
                'integer',
                Rule::exists('check_agents', 'id')->where('user_id', Auth::id()),
            ],
            'kind' => ['required', 'string', Rule::in([AddressKind::Http->value, AddressKind::OpenApi->value])],
            'ignoreJsonPaths' => ['nullable', 'string'],
            'ignoreHeaders' => ['nullable', 'string'],
            'ignoreBodyRegex' => ['nullable', 'string'],
            'watchJsonPaths' => ['nullable', 'string'],
            'assertionsJson' => ['nullable', 'string'],
            'stepOrder' => ['nullable', 'integer', 'min:1'],
            'extractJsonPath' => ['nullable', 'string', 'max:255'],
            'extractAs' => ['nullable', 'string', 'max:64'],
        ], $this->methodAndBodyRules()));

        $this->address->update([
            'http_method' => $validated['http_method'],
            'request_headers' => $this->normalizeRequestHeaders($validated['headers'] ?? []),
            'request_body' => $this->resolvedRequestBody(),
            'site_token_id' => $this->resolvedSiteTokenId($validated['siteTokenId'] ?? null),
            'check_agent_id' => $this->resolvedSiteTokenId($validated['checkAgentId'] ?? null),
            'kind' => $validated['kind'],
            'ignore_json_paths' => $this->lines($validated['ignoreJsonPaths'] ?? ''),
            'ignore_headers' => $this->lines($validated['ignoreHeaders'] ?? ''),
            'ignore_body_regex' => $this->lines($validated['ignoreBodyRegex'] ?? ''),
            'watch_json_paths' => $this->lines($validated['watchJsonPaths'] ?? ''),
            'assertions' => $this->decodeAssertions($validated['assertionsJson'] ?? ''),
            'step_order' => $validated['stepOrder'] ?? null,
            'extract_json_path' => $validated['extractJsonPath'] ?: null,
            'extract_as' => $validated['extractAs'] ?: null,
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
        $this->siteTokenId = $this->address->site_token_id;
        $this->checkAgentId = $this->address->check_agent_id;
        $this->kind = $this->address->kind?->value ?? AddressKind::Http->value;
        $this->ignoreJsonPaths = $this->toLines($this->address->ignore_json_paths);
        $this->ignoreHeaders = $this->toLines($this->address->ignore_headers);
        $this->ignoreBodyRegex = $this->toLines($this->address->ignore_body_regex);
        $this->watchJsonPaths = $this->toLines($this->address->watch_json_paths);
        $this->assertionsJson = $this->encodeAssertions($this->address->assertions);
        $this->stepOrder = $this->address->step_order;
        $this->extractJsonPath = (string) ($this->address->extract_json_path ?? '');
        $this->extractAs = (string) ($this->address->extract_as ?? '');
    }

    public function render(): View
    {
        $this->site->loadMissing(['tokens' => fn ($q) => $q->orderBy('id')]);
        $agents = CheckAgent::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        return view('livewire.addresses.address-settings-modal', [
            'agents' => $agents,
        ]);
    }

    /**
     * @return list<string>
     */
    private function lines(string $value): array
    {
        $lines = [];

        foreach (preg_split("/\r\n|\n|\r/", $value) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  list<string>|null  $items
     */
    private function toLines(?array $items): string
    {
        return implode("\n", $items ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeAssertions(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<array<string, mixed>>|null  $assertions
     */
    private function encodeAssertions(?array $assertions): string
    {
        if ($assertions === null || $assertions === []) {
            return '';
        }

        return json_encode($assertions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function resolvedSiteTokenId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
