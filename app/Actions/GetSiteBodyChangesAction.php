<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\AddressBodyChangeDTO;
use App\DTOs\DiffOptionsDTO;
use App\Models\Address;
use App\Models\Site;
use App\Services\DiffService;
use Illuminate\Support\Collection;

final class GetSiteBodyChangesAction
{
    public function __construct(private readonly DiffService $diffService) {}

    /**
     * @return Collection<int, AddressBodyChangeDTO>
     */
    public function execute(Site $site): Collection
    {
        return $this->addressesFor($site)
            ->map(fn (Address $address): ?AddressBodyChangeDTO => $this->changeFor($site, $address))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Address>
     */
    private function addressesFor(Site $site): Collection
    {
        return $site->addresses()
            ->with(['latestSnapshot', 'previousSnapshot', 'siteToken'])
            ->orderBy('id')
            ->get();
    }

    private function changeFor(Site $site, Address $address): ?AddressBodyChangeDTO
    {
        $address->setRelation('site', $site);
        $latest = $address->latestSnapshot;
        $previous = $address->previousSnapshot;

        if ($latest === null || $previous === null || $latest->body_hash === $previous->body_hash) {
            return null;
        }

        $body = $this->diffService->compare($previous, $latest, DiffOptionsDTO::fromAddress($address))['body'];

        if (! $body['changed']) {
            return null;
        }

        return new AddressBodyChangeDTO(
            address: $address,
            latest: $latest,
            previous: $previous,
            body: $this->bodyPayload($body),
        );
    }

    /**
     * @param  array{
     *     type: string,
     *     changed: bool,
     *     changes: list<array{path: string, type: string, old: mixed, new: mixed}>,
     *     text_diff: list<string>
     * }  $body
     * @return array{
     *     type: string,
     *     changes: list<array{path: string, type: string, old: mixed, new: mixed}>,
     *     text_diff: list<string>
     * }
     */
    private function bodyPayload(array $body): array
    {
        return [
            'type' => $body['type'],
            'changes' => $body['changes'],
            'text_diff' => $body['text_diff'],
        ];
    }
}
