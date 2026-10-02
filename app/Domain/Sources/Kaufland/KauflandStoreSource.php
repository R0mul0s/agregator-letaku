<?php

/**
 * Seznam prodejen Kauflandu z vyhledávače prodejen (.klstorefinder.json, ZDROJE_DAT.md).
 *
 * Položka: `n` ID prodejny (CZ1000), `cn` název, `t` město, `sn` ulice, `lat`/`lng` jako text.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kaufland;

use App\Domain\Sources\Data\StoreData;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\SourceHttp;
use App\Domain\Sources\StoreSource;
use App\Enums\Chain;

final class KauflandStoreSource implements StoreSource
{
    public function __construct(private readonly SourceHttp $http) {}

    /**
     * Kaufland.
     */
    public function chain(): Chain
    {
        return Chain::Kaufland;
    }

    /**
     * Všechny prodejny z vyhledávače.
     */
    public function fetch(): array
    {
        $stores = $this->http->request()->get(config()->string('letaky.sources.kaufland.stores_url'))->json();

        if (! is_array($stores) || ! array_is_list($stores) || $stores === []) {
            throw SourceResponseChanged::because(Chain::Kaufland, 'seznam prodejen není neprázdné pole');
        }

        return array_map($this->store(...), $stores);
    }

    /**
     * Jedna prodejna.
     *
     * @throws SourceResponseChanged
     */
    private function store(mixed $store): StoreData
    {
        if (! is_array($store) || ! is_string($store['n'] ?? null) || ! is_string($store['cn'] ?? null)) {
            throw SourceResponseChanged::because(Chain::Kaufland, 'prodejna bez ID nebo názvu');
        }

        return new StoreData(
            externalId: $store['n'],
            name: $store['cn'],
            city: is_string($store['t'] ?? null) ? $store['t'] : null,
            address: is_string($store['sn'] ?? null) ? $store['sn'] : null,
            latitude: is_numeric($store['lat'] ?? null) ? (float) $store['lat'] : null,
            longitude: is_numeric($store['lng'] ?? null) ? (float) $store['lng'] : null,
        );
    }
}
