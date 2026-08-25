<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\ImportAgentAddressesDTO;
use App\DTOs\ImportAgentAddressesResultDTO;
use App\Models\Address;
use App\Models\Site;
use App\Models\User;
use App\Services\PlanQuota;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ImportAgentAddressesAction
{
    public function __construct(
        private PlanQuota $quota,
    ) {}

    public function execute(User $user, Site $site, ImportAgentAddressesDTO $dto): ImportAgentAddressesResultDTO
    {
        $normalized = $this->uniqueNormalizedEndpoints($site, $dto->endpoints);
        $existing = $this->existingEndpoints($site, $normalized);
        $toCreate = array_values(array_diff($normalized, $existing));
        $skipped = count($dto->endpoints) - count($toCreate);

        if ($toCreate !== []) {
            $this->quota->assertCanCreateAddresses($user, $site, count($toCreate));
        }

        $created = $this->createAddresses($site, $toCreate, $dto->scheduleEnabled);

        return new ImportAgentAddressesResultDTO(
            created: $created->count(),
            skipped: $skipped,
            addresses: $created,
        );
    }

    /**
     * @param  list<string>  $rawEndpoints
     * @return list<string>
     */
    private function uniqueNormalizedEndpoints(Site $site, array $rawEndpoints): array
    {
        $endpoints = [];

        foreach ($rawEndpoints as $raw) {
            $endpoint = $this->normalizeEndpoint($site, $raw);

            if ($endpoint === null || in_array($endpoint, $endpoints, true)) {
                continue;
            }

            $endpoints[] = $endpoint;
        }

        return $endpoints;
    }

    /**
     * @param  list<string>  $endpoints
     * @return list<string>
     */
    private function existingEndpoints(Site $site, array $endpoints): array
    {
        if ($endpoints === []) {
            return [];
        }

        return $site->addresses()
            ->whereIn('endpoint', $endpoints)
            ->pluck('endpoint')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $endpoints
     * @return Collection<int, Address>
     */
    private function createAddresses(Site $site, array $endpoints, bool $scheduleEnabled): Collection
    {
        if ($endpoints === []) {
            return new Collection;
        }

        return DB::transaction(function () use ($site, $endpoints, $scheduleEnabled): Collection {
            $created = new Collection;

            foreach ($endpoints as $endpoint) {
                $address = $site->addresses()->create([
                    'endpoint' => $endpoint,
                    'http_method' => 'GET',
                    'schedule_enabled' => $scheduleEnabled,
                ]);
                $address->setRelation('site', $site);
                $created->push($address);
            }

            return $created;
        });
    }

    private function normalizeEndpoint(Site $site, string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return $this->endpointFromAbsoluteUrl($site, $raw);
        }

        return $this->clampEndpoint($this->ensureLeadingSlash($raw));
    }

    private function endpointFromAbsoluteUrl(Site $site, string $url): ?string
    {
        $page = parse_url($url);
        $base = parse_url(rtrim((string) $site->base_url, '/').'/');

        if (! is_array($page) || ! is_array($base)) {
            return null;
        }

        $pageOrigin = $this->origin($page);
        $baseOrigin = $this->origin($base);

        if ($pageOrigin === null || $pageOrigin !== $baseOrigin) {
            return null;
        }

        $relative = $this->relativePath(
            (string) ($page['path'] ?? '/'),
            rtrim((string) ($base['path'] ?? ''), '/'),
        );

        if ($relative === null) {
            return null;
        }

        $query = isset($page['query']) && $page['query'] !== ''
            ? '?'.$page['query']
            : '';

        return $this->clampEndpoint($relative.$query);
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function origin(array $parsed): ?string
    {
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));

        if ($scheme === '' || $host === '') {
            return null;
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : 80;

        if ($port === null || $port === $defaultPort) {
            return $scheme.'://'.$host;
        }

        return $scheme.'://'.$host.':'.$port;
    }

    private function relativePath(string $pagePath, string $basePath): ?string
    {
        $pagePath = $pagePath === '' ? '/' : $pagePath;

        if ($basePath === '' || $basePath === '/') {
            return $this->ensureLeadingSlash($pagePath);
        }

        if ($pagePath === $basePath || $pagePath === $basePath.'/') {
            return '/';
        }

        $prefix = $basePath.'/';

        if (! str_starts_with($pagePath, $prefix)) {
            return null;
        }

        $relative = substr($pagePath, strlen($basePath));

        return $this->ensureLeadingSlash($relative === '' ? '/' : $relative);
    }

    private function ensureLeadingSlash(string $endpoint): string
    {
        if (! str_starts_with($endpoint, '/')) {
            return '/'.$endpoint;
        }

        return $endpoint;
    }

    private function clampEndpoint(string $endpoint): ?string
    {
        $maxLength = max(1, (int) config('checking.address_endpoint_max', 766));

        if (strlen($endpoint) > $maxLength) {
            return null;
        }

        return $endpoint;
    }
}
