<?php

/**
 * Zdroj akční nabídky e-shopu Rohlik.cz — JSON API webu (R180, ZDROJE_DAT.md, Rohlík).
 *
 * Ve výchozím stavu vypnutý (`enabled_by_default` false): obchodní podmínky Rohlíku zakazují bez
 * písemného souhlasu automatizované získávání obsahu. Zapne ho `LETAKY_ENABLED_CHAINS=rohlik`.
 *
 * Postup: ID produktů v akci po hlavních odděleních (`categories/sales/{id}/products`, stránky po 100),
 * k nim po dávkách ceny s typy akcí (`products/prices`) a detaily (`products`). Produkt bez akce, kterou
 * bereme (jen Xtra, dětský klub…), se vynechá; chybí-li u víc než `max_missing_share` produktů detail
 * nebo konec akce, změnilo se API a stažení skončí chybou (zbytek by import označil jako stažený, R16).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

namespace App\Domain\Sources\Rohlik;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Illuminate\Http\Client\RequestException;

final class RohlikOfferSource implements OfferSource
{
    /** Externí ID zdroje „akce e-shopu“ — průběžné akce bez letáku. */
    private const ESHOP_LEAFLET_ID = 'akce';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly RohlikParser $parser,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Rohlík.
     */
    public function chain(): Chain
    {
        return Chain::Rohlik;
    }

    /**
     * Jedna dávka „akce e-shopu“ se všemi akcemi hlavních oddělení.
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    public function fetch(): array
    {
        // ID => oddělení, ve kterém se produkt našel první
        $departments = [];
        foreach (config()->array('letaky.sources.rohlik.departments') as $department => $name) {
            foreach ($this->departmentIds((int) $department) as $id) {
                $departments[$id] ??= (string) $name;
            }
        }

        $today = $this->calendar->today();
        $offers = [];
        $withSale = 0;
        $missing = 0;
        foreach (array_chunk(array_keys($departments), max(1, config()->integer('letaky.sources.rohlik.batch_size'))) as $ids) {
            $prices = $this->parser->byId($this->batch('prices_path', $ids), 'productId', 'ceny produktů');
            $products = $this->parser->byId($this->batch('products_path', $ids), 'id', 'produkty');

            foreach ($ids as $id) {
                $price = $prices[$id] ?? null;
                // Bez akce pro všechny (jen Xtra, dětský klub…) nebo se skončenou akcí je běžný stav,
                // chybějící ceny v odpovědi jsou chyba API
                if ($price !== null && (! $this->parser->hasOfferSale($price) || $this->parser->hasEnded($price, $today))) {
                    continue;
                }
                $withSale++;
                if ($price === null) {
                    $missing++;

                    continue;
                }

                $product = $products[$id] ?? null;
                if ($product !== null && ! $this->isOwnProduct($product)) {
                    continue;
                }
                $offer = $product === null ? null : $this->parser->offer($product, $price, $departments[$id], $today);
                if ($offer === null) {
                    $missing++;

                    continue;
                }
                $offers[] = $offer;
            }
        }

        $maxMissing = config()->float('letaky.sources.rohlik.max_missing_share');
        if ($missing > $withSale * $maxMissing) {
            throw SourceResponseChanged::because(Chain::Rohlik, "{$missing} z {$withSale} produktů v akci nejde převést (ceny, detail nebo konec akce)");
        }

        return [new SourceBatch(new LeafletData(
            kind: LeafletKind::Eshop,
            externalId: self::ESHOP_LEAFLET_ID,
            sourceUrl: config()->string('letaky.sources.rohlik.offers_page_url'),
        ), $offers)];
    }

    /**
     * ID všech produktů v akci oddělení, po stránkách, dokud stránka není kratší než velikost stránky.
     *
     * @return list<int>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    private function departmentIds(int $department): array
    {
        $size = config()->integer('letaky.sources.rohlik.page_size');
        $maxPages = config()->integer('letaky.sources.rohlik.max_pages_per_department');
        $url = config()->string('letaky.sources.rohlik.base_url').sprintf(config()->string('letaky.sources.rohlik.sales_ids_path'), $department);

        $ids = [];
        for ($page = 0; ; $page++) {
            if ($page >= $maxPages) {
                throw SourceResponseChanged::because(Chain::Rohlik, "akce oddělení {$department} mají víc než {$maxPages} stránek");
            }
            $response = $this->http->request($this->delay())->get($url, ['page' => $page, 'size' => $size, 'sort' => 'recommended'])->json();
            $pageIds = $this->parser->productIds(is_array($response) ? $response : [], $department);
            array_push($ids, ...$pageIds);
            if (count($pageIds) < $size) {
                return $ids;
            }
        }
    }

    /**
     * Odpověď pro dávku produktů (`?products=1&products=2…` — PHP pole `products[]` API nebere).
     *
     * @param  list<int>  $ids
     * @return array<mixed>
     *
     * @throws RequestException
     */
    private function batch(string $pathKey, array $ids): array
    {
        $query = implode('&', array_map(fn (int $id): string => 'products='.$id, $ids));
        $response = $this->http->request($this->delay())
            ->get(config()->string('letaky.sources.rohlik.base_url').config()->string("letaky.sources.rohlik.{$pathKey}").'?'.$query)
            ->json();

        return is_array($response) ? $response : [];
    }

    /**
     * Prodává produkt Rohlík všem (ne partner a ne jen pro předplatitele Xtra)?
     *
     * @param  array<string, mixed>  $product
     */
    private function isOwnProduct(array $product): bool
    {
        return $this->parser->sellerId($product) === config()->integer('letaky.sources.rohlik.seller_id')
            && ($product['premiumOnly'] ?? false) !== true;
    }

    /**
     * Pauza mezi požadavky — ~180 malých odpovědí.
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.rohlik.request_delay_ms');
    }
}
