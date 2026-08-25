<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\Site;
use App\Models\SiteToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

final class SiteTransferService
{
    public const FORMAT = 'api-checker-sites';

    public function __construct(private PlanQuota $quota) {}

    /**
     * @return array{format: string, version: int, exported_at: string, sites: list<array<string, mixed>>}
     */
    public function exportSite(Site $site): array
    {
        $site->load($this->transferRelations());

        return $this->wrapPayload([$this->siteToArray($site)]);
    }

    /**
     * @return array{format: string, version: int, exported_at: string, sites: list<array<string, mixed>>}
     */
    public function exportAll(?User $user = null): array
    {
        $query = Site::query()
            ->with($this->transferRelations())
            ->orderBy('id');

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        $sites = $query->get();

        return $this->wrapPayload($sites->map($this->siteToArray(...))->all());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        );
    }

    public function filenameForSite(Site $site): string
    {
        $slug = Str::slug($site->name) ?: 'site';

        return $slug.'-'.$site->id.'-'.now()->format('Y-m-d').'.json';
    }

    public function filenameForAll(): string
    {
        return 'sites-export-'.now()->format('Y-m-d').'.json';
    }

    public function copy(Site $site, User $user): Site
    {
        $site->load($this->transferRelations());

        $this->quota->assertCanCreateSite($user);
        $this->quota->assertCanCreateAddressesOnNewSite($user, $site->addresses->count());

        $payload = $this->siteToArray($site);
        $payload['name'] = $site->name.' (копія)';

        return DB::transaction(fn (): Site => $this->createSite($payload, $user));
    }

    /**
     * @return Collection<int, Site>
     */
    public function importJson(string $json, User $user): Collection
    {
        $sites = $this->validatedSites($this->decodePayload($json));
        $this->quota->assertCanImport($user, $sites);

        return DB::transaction(function () use ($sites, $user) {
            return collect($sites)->map(fn (array $site): Site => $this->createSite($site, $user));
        });
    }

    /**
     * @return Collection<int, Site>
     */
    public function importFile(string $path, User $user): Collection
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw ValidationException::withMessages([
                'file' => 'Не вдалося прочитати файл імпорту.',
            ]);
        }

        return $this->importJson($json, $user);
    }

    /**
     * @param  list<array<string, mixed>>  $sites
     * @return array{format: string, version: int, exported_at: string, sites: list<array<string, mixed>>}
     */
    private function wrapPayload(array $sites): array
    {
        return [
            'format' => $this->formatName(),
            'version' => $this->formatVersion(),
            'exported_at' => now()->toIso8601String(),
            'sites' => $sites,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function siteToArray(Site $site): array
    {
        return [
            'name' => $site->name,
            'base_url' => $site->base_url,
            'schedule_enabled' => (bool) $site->schedule_enabled,
            'schedule_interval' => $site->schedule_interval,
            'requests_per_minute' => $site->requests_per_minute,
            'tokens' => $site->tokens
                ->map(fn (SiteToken $token): array => [
                    'name' => $token->name,
                    'value' => $token->value,
                ])
                ->values()
                ->all(),
            'addresses' => $site->addresses->map($this->addressToArray(...))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressToArray(Address $address): array
    {
        return [
            'name' => $address->name,
            'endpoint' => $address->endpoint,
            'http_method' => $address->http_method,
            'schedule_enabled' => (bool) $address->schedule_enabled,
            'request_headers' => $address->request_headers ?? [],
            'request_body' => $address->request_body,
            'token' => $address->siteToken?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'file' => 'Файл не є коректним JSON.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'file' => 'Файл імпорту має невірну структуру.',
            ]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function validatedSites(array $payload): array
    {
        $this->assertFormat($payload);

        $sites = $this->extractSites($payload);
        $validated = [];

        foreach ($sites as $index => $site) {
            if (! is_array($site)) {
                throw ValidationException::withMessages([
                    'file' => 'Некоректний запис сайту в файлі імпорту.',
                ]);
            }

            $validated[] = $this->validateSitePayload($site, (int) $index);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractSites(array $payload): array
    {
        $sites = $payload['sites'] ?? null;

        if (! is_array($sites) && isset($payload['site']) && is_array($payload['site'])) {
            $sites = [$payload['site']];
        }

        if (! is_array($sites) || $sites === []) {
            throw ValidationException::withMessages([
                'file' => 'У файлі немає сайтів для імпорту.',
            ]);
        }

        return array_values($sites);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertFormat(array $payload): void
    {
        if (($payload['format'] ?? null) !== $this->formatName()) {
            throw ValidationException::withMessages([
                'file' => 'Це не файл експорту API Snapshot Checker.',
            ]);
        }

        if ((int) ($payload['version'] ?? 0) !== $this->formatVersion()) {
            throw ValidationException::withMessages([
                'file' => 'Непідтримувана версія файлу експорту.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    private function validateSitePayload(array $site, int $index): array
    {
        $validator = Validator::make($site, $this->siteRules());

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'file' => 'Сайт #'.($index + 1).': '.$validator->errors()->first(),
            ]);
        }

        $validated = $validator->validated();
        $validated['tokens'] = $this->validateTokens($site['tokens'] ?? [], $index);
        $validated['addresses'] = $this->validateAddresses(
            $site['addresses'] ?? [],
            $index,
            array_column($validated['tokens'], 'name'),
        );

        return $validated;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function siteRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:2048'],
            'schedule_enabled' => ['sometimes', 'boolean'],
            'schedule_interval' => ['nullable', 'string', Rule::in(array_keys(Site::SCHEDULE_INTERVALS))],
            'requests_per_minute' => [
                'nullable',
                'integer',
                'min:'.Site::CHECKS_PER_MINUTE_MIN,
                'max:'.Site::CHECKS_PER_MINUTE_MAX,
            ],
            'addresses' => ['nullable', 'array'],
        ];
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function validateTokens(mixed $tokens, int $siteIndex): array
    {
        if ($tokens === null) {
            return [];
        }

        if (! is_array($tokens)) {
            throw ValidationException::withMessages([
                'file' => 'Сайт #'.($siteIndex + 1).': некоректний список токенів.',
            ]);
        }

        $validated = [];

        foreach (array_values($tokens) as $index => $token) {
            if (! is_array($token)) {
                throw ValidationException::withMessages([
                    'file' => 'Сайт #'.($siteIndex + 1).': некоректний токен.',
                ]);
            }

            $validator = Validator::make($token, [
                'name' => ['required', 'string', 'max:255'],
                'value' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    'file' => 'Сайт #'.($siteIndex + 1).', токен #'.($index + 1).': '.$validator->errors()->first(),
                ]);
            }

            $validated[] = $validator->validated();
        }

        $names = array_column($validated, 'name');

        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages([
                'file' => 'Сайт #'.($siteIndex + 1).': назви токенів мають бути унікальними.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  list<string>  $tokenNames
     * @return list<array<string, mixed>>
     */
    private function validateAddresses(mixed $addresses, int $siteIndex, array $tokenNames): array
    {
        if ($addresses === null) {
            return [];
        }

        if (! is_array($addresses)) {
            throw ValidationException::withMessages([
                'file' => 'Сайт #'.($siteIndex + 1).': некоректний список адрес.',
            ]);
        }

        $validated = [];

        foreach (array_values($addresses) as $index => $address) {
            $validated[] = $this->validateAddressPayload($address, $siteIndex, $index, $tokenNames);
        }

        return $validated;
    }

    /**
     * @param  list<string>  $tokenNames
     * @return array<string, mixed>
     */
    private function validateAddressPayload(mixed $address, int $siteIndex, int $index, array $tokenNames): array
    {
        if (! is_array($address)) {
            throw ValidationException::withMessages([
                'file' => 'Сайт #'.($siteIndex + 1).': некоректна адреса.',
            ]);
        }

        $validator = Validator::make($address, $this->addressRules());

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'file' => 'Сайт #'.($siteIndex + 1).', адреса #'.($index + 1).': '.$validator->errors()->first(),
            ]);
        }

        $validated = $validator->validated();
        $validated['request_headers'] = $this->normalizeHeaders($validated['request_headers'] ?? []);
        $tokenName = $validated['token'] ?? null;

        if ($tokenName !== null && $tokenName !== '' && ! in_array($tokenName, $tokenNames, true)) {
            throw ValidationException::withMessages([
                'file' => 'Сайт #'.($siteIndex + 1).', адреса #'.($index + 1).': токен не знайдено.',
            ]);
        }

        $validated['token'] = $tokenName === '' ? null : $tokenName;

        return $validated;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function addressRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['required', 'string', 'max:2048'],
            'http_method' => ['nullable', 'string', Rule::in(Address::METHODS)],
            'schedule_enabled' => ['sometimes', 'boolean'],
            'request_headers' => ['nullable', 'array'],
            'request_body' => ['nullable', 'string'],
            'token' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createSite(array $payload, User $user): Site
    {
        $site = $user->sites()->create([
            'name' => $payload['name'],
            'base_url' => rtrim((string) $payload['base_url'], '/'),
            'schedule_enabled' => (bool) ($payload['schedule_enabled'] ?? false),
            'schedule_interval' => $payload['schedule_interval'] ?? null,
            'schedule_last_run_at' => null,
            'requests_per_minute' => $payload['requests_per_minute'] ?? null,
        ]);

        $tokenIdsByName = $this->createTokens($site, $payload['tokens'] ?? []);
        $this->createAddresses($site, $payload['addresses'] ?? [], $tokenIdsByName);

        return $site;
    }

    /**
     * @param  list<array{name: string, value: string}>  $tokens
     * @return array<string, int>
     */
    private function createTokens(Site $site, array $tokens): array
    {
        $idsByName = [];

        foreach ($tokens as $token) {
            $created = $site->tokens()->create([
                'name' => $token['name'],
                'value' => $token['value'],
            ]);
            $idsByName[$created->name] = $created->id;
        }

        return $idsByName;
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     * @param  array<string, int>  $tokenIdsByName
     */
    private function createAddresses(Site $site, array $addresses, array $tokenIdsByName): void
    {
        foreach ($addresses as $address) {
            $tokenName = $address['token'] ?? null;
            $site->addresses()->create([
                'name' => $address['name'] ?? null,
                'endpoint' => $this->normalizeEndpoint((string) $address['endpoint']),
                'http_method' => $address['http_method'] ?? 'GET',
                'schedule_enabled' => (bool) ($address['schedule_enabled'] ?? true),
                'request_headers' => $address['request_headers'] ?? [],
                'request_body' => $address['request_body'] ?? null,
                'last_checked_at' => null,
                'site_token_id' => is_string($tokenName) && $tokenName !== ''
                    ? ($tokenIdsByName[$tokenName] ?? null)
                    : null,
            ]);
        }
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            return '/';
        }

        if (! str_starts_with($endpoint, '/')) {
            $endpoint = '/'.$endpoint;
        }

        return $endpoint;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name) || $name === '' || ! is_scalar($value)) {
                continue;
            }

            $normalized[$name] = (string) $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function transferRelations(): array
    {
        return [
            'tokens' => fn ($query) => $query->orderBy('id'),
            'addresses' => fn ($query) => $query->orderBy('id')->with('siteToken'),
        ];
    }

    private function formatName(): string
    {
        return (string) config('checking.transfer.format', self::FORMAT);
    }

    private function formatVersion(): int
    {
        return (int) config('checking.transfer.version', 1);
    }
}
