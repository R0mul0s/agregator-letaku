<?php

/**
 * Zdroj akční nabídky Globusu — veřejné REST API webu (R46, ZDROJE_DAT.md, Globus).
 *
 * Akce s cenou a platností jsou v katalogu akcí jednoho hypermarketu; položky letáku
 * se stahují jen kvůli popisu („různé druhy“), cenu nesou ty z katalogu. Akce mají
 * různou platnost (týden, dva týdny, měsíc), proto jsou všechny v jednom průběžném
 * zdroji „akční nabídka“ jako akce e-shopu Tesca.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Globus;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;

final class GlobusOfferSource implements OfferSource
{
    /** Externí ID průběžného zdroje akcí (jediný, akce si nesou vlastní platnost). */
    private const LEAFLET_ID = 'akcni-nabidka';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly GlobusParser $parser,
    ) {}

    /**
     * Globus.
     */
    public function chain(): Chain
    {
        return Chain::Globus;
    }

    /**
     * Jedna dávka se všemi akcemi katalogu.
     */
    public function fetch(): array
    {
        $descriptions = $this->parser->descriptionsByEan($this->leafletItems());

        $offers = [];
        foreach ($this->catalogProducts() as $product) {
            $offer = $this->parser->offer($product, $descriptions);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return [new SourceBatch(
            new LeafletData(
                kind: LeafletKind::Web,
                externalId: self::LEAFLET_ID,
                sourceUrl: config()->string('letaky.sources.globus.offers_page_url'),
            ),
            $offers,
        )];
    }

    /**
     * Všechny položky katalogu akcí. Stránkuje se podle `paginationShowMore` — `totalCount` nesedí.
     *
     * @return list<array<string, mixed>>
     */
    private function catalogProducts(): array
    {
        $products = [];
        $page = 0;
        do {
            $result = $this->parser->catalogPage($this->get('catalog_path', $page));
            array_push($products, ...$result['products']);
            $page++;
        } while ($result['hasMore'] && $result['products'] !== []);

        return $products;
    }

    /**
     * Všechny položky letáků; poslední stránka je kratší než plná.
     *
     * @return list<array<string, mixed>>
     */
    private function leafletItems(): array
    {
        $pageSize = config()->integer('letaky.sources.globus.page_size');
        $items = [];
        $page = 0;
        do {
            $pageItems = $this->parser->leafletItemsPage($this->get('leaflet_items_path', $page));
            array_push($items, ...$pageItems);
            $page++;
        } while (count($pageItems) === $pageSize);

        return $items;
    }

    /**
     * Stránka výpisu API hypermarketu z konfigurace.
     *
     * @param  string  $pathKey  Klíč cesty v konfiguraci zdroje
     * @return array<mixed>
     */
    private function get(string $pathKey, int $page): array
    {
        $url = sprintf(config()->string('letaky.sources.globus.api_url'), config()->integer('letaky.sources.globus.house_id'))
            .config()->string("letaky.sources.globus.{$pathKey}");
        $response = $this->http->request()
            ->get($url, ['page' => $page, 'pageSize' => config()->integer('letaky.sources.globus.page_size')])
            ->json();

        return is_array($response) ? $response : [];
    }
}
