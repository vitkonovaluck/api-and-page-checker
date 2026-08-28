<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\AddressKind;
use App\Models\Address;

final readonly class DiffOptionsDTO
{
    /**
     * @param  list<string>  $ignoreJsonPaths
     * @param  list<string>  $ignoreHeaders
     * @param  list<string>  $ignoreBodyRegex
     * @param  list<string>  $watchJsonPaths
     */
    public function __construct(
        public array $ignoreJsonPaths = [],
        public array $ignoreHeaders = [],
        public array $ignoreBodyRegex = [],
        public array $watchJsonPaths = [],
        public AddressKind $kind = AddressKind::Http,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function fromAddress(Address $address): self
    {
        $kind = $address->kind instanceof AddressKind
            ? $address->kind
            : AddressKind::tryFrom((string) $address->kind) ?? AddressKind::Http;

        return new self(
            ignoreJsonPaths: self::stringList($address->ignore_json_paths),
            ignoreHeaders: self::stringList($address->ignore_headers),
            ignoreBodyRegex: self::stringList($address->ignore_body_regex),
            watchJsonPaths: self::stringList($address->watch_json_paths),
            kind: $kind,
        );
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $paths = [];

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                continue;
            }

            $paths[] = trim($item);
        }

        return $paths;
    }
}
