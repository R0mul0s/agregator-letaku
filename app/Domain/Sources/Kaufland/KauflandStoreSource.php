<?php

/**
 * Prodejny Kauflandu a akce platné v každé z nich (R49, ZDROJE_DAT.md, Kaufland).
 *
 * Seznam prodejen je `.klstorefinder.json` (149 prodejen), akce prodejny malý JSON
 * `.kloffers.storeName={kód}.json` jen s `klNr` a platností — přesně ty akce, které má stránka
 * nabídky s cookie prodejny. Klíč akce je stejný jako OfferData::key() u Kauflandu
 * (externí ID je `klNr`, platnost místní data).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kaufland;

use App\Domain\Offers\Parsing\Text;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\SourceHttp;
use App\Domain\Sources\StoreSource;
use App\Enums\Chain;

final class KauflandStoreSource implements StoreSource
{
    /** Oddělovač částí klíče nabídky (OfferData::key). */
    private const KEY_SEPARATOR = '|';

    public function __construct(private readonly SourceHttp $http) {}

    /**
     * Kaufland.
     */
    public function chain(): Chain
    {
        return Chain::Kaufland;
    }

    /**
     * Prodejny z `.klstorefinder.json`: `n` = kód, `cn` = „Kaufland Trutnov“, `t` = město.
     */
    public function stores(): array
    {
        $response = $this->http->request()->get(config()->string('letaky.sources.kaufland.stores_url'))->json();
        if (! is_array($response) || ! array_is_list($response)) {
            throw SourceResponseChanged::because(Chain::Kaufland, 'seznam prodejen není pole');
        }

        $prefix = config()->string('letaky.sources.kaufland.store_name_prefix');
        $stores = [];
        foreach ($response as $store) {
            $code = is_array($store) ? ($store['n'] ?? null) : null;
            $name = is_array($store) ? Text::clean(is_string($store['cn'] ?? null) ? $store['cn'] : null) : null;
            $city = is_array($store) ? Text::clean(is_string($store['t'] ?? null) ? $store['t'] : null) : null;
            if (is_string($code) && $name !== null && $city !== null) {
                $stores[$code] = ['name' => str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name, 'city' => $city];
            }
        }

        return $stores === [] ? throw SourceResponseChanged::because(Chain::Kaufland, 'seznam prodejen je prázdný') : $stores;
    }

    /**
     * Akce prodejny jako „klNr|od|do“.
     */
    public function offerKeys(string $storeCode): array
    {
        $url = config()->string('letaky.sources.kaufland.store_offers_base_url').sprintf(config()->string('letaky.sources.kaufland.store_offers_path'), $storeCode);
        $response = $this->http->request(config()->integer('letaky.sources.kaufland.store_offers_delay_ms'))->get($url)->json();
        if (! is_array($response) || ! array_is_list($response)) {
            throw SourceResponseChanged::because(Chain::Kaufland, "akce prodejny {$storeCode} nejsou pole");
        }

        $keys = [];
        foreach ($response as $item) {
            $klNr = is_array($item) ? ($item['klNr'] ?? null) : null;
            $from = is_array($item) ? ($item['dateFrom'] ?? null) : null;
            $to = is_array($item) ? ($item['dateTo'] ?? null) : null;
            if (is_string($klNr) && is_string($from) && is_string($to)) {
                $keys[] = implode(self::KEY_SEPARATOR, [$klNr, $from, $to]);
            }
        }

        return array_values(array_unique($keys));
    }
}
