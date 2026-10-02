<?php

/**
 * Zdroj akční nabídky Tesco — akce e-shopu (xapi) a letáky hypermarketů a supermarketů
 * (leaflets-be), viz ZDROJE_DAT.md.
 *
 * Ceny jsou jen v e-shopu. Leták určuje, kde akce platí: produkt z letáku HM nebo SM platí
 * v prodejnách daného formátu, v obou letácích ve všech prodejnách; produkt, který v žádném
 * letáku není, je jen online (R4). Produkty letáku bez akce v e-shopu („Super cena“) chybí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Tesco;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\Exceptions\SourceNotConfigured;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Carbon\CarbonImmutable;

final class TescoOfferSource implements OfferSource
{
    private const COUNTRY = 'cz';

    /** Externí ID zdroje „akce e-shopu“ — průběžné akce bez letáku. */
    private const ESHOP_LEAFLET_ID = 'eshop';

    private const LEAFLETS_QUERY = 'query ValidLeaflets($country: CountryCode!, $currentDate: DateTime!) { leaflets(options: { filter: { country: { eq: $country }, validTo: { after: $currentDate } } }) { items { id slug leafletUrl validFrom validTo type } } }';

    private const LEAFLET_PRODUCTS_QUERY = 'query LeafletProducts($country: CountryCode!, $slug: String!, $leafletType: LeafletType) { leafletBySlug(country: $country, slug: $slug, leafletType: $leafletType) { pages { positions { products { addToBasketURL } } } } }';

    private const PROMOTIONS_QUERY = 'query Promotions($promotion: String, $page: Int, $count: Int) { promotions: promotionType(type: $promotion, page: $page, count: $count) { info { total } products { id title brandName superDepartmentName departmentName defaultImageUrl price { actual unitPrice unitOfMeasure } promotions { id startDate endDate description unitSellingInfo price { beforeDiscount afterDiscount } attributes } } } }';

    /** Typ výpisu akcí e-shopu — všechny akce. */
    private const ALL_PROMOTIONS = 'all';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly TescoParser $parser,
    ) {}

    /**
     * Tesco.
     */
    public function chain(): Chain
    {
        return Chain::Tesco;
    }

    /**
     * Dávka za každý leták a dávka akcí jen online.
     */
    public function fetch(): array
    {
        $apiKey = config('letaky.sources.tesco.eshop_api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw SourceNotConfigured::missing(Chain::Tesco, 'TESCO_API_KEY');
        }

        $leaflets = $this->leafletsWithProducts();
        $offersByLeaflet = array_fill_keys(array_keys($leaflets), []);
        $onlineOnly = [];

        foreach ($this->promotionProducts($apiKey) as $product) {
            foreach ($this->parser->offers($product, config()->string('letaky.sources.tesco.eshop_product_url')) as $offer) {
                $matching = $this->matchingLeaflets($offer, $leaflets);
                if ($matching === []) {
                    $onlineOnly[] = $offer->withAvailability(onlineOnly: true, storeFormat: null);

                    continue;
                }

                // V letáku HM i SM = všechny prodejny; nabídka patří k prvnímu z nich
                $first = array_key_first($matching);
                $format = count($matching) === 1 ? $leaflets[$first]['leaflet']->data->format : null;
                $offersByLeaflet[$first][] = $offer->withAvailability(onlineOnly: false, storeFormat: $format);
            }
        }

        $batches = [new SourceBatch($this->eshopLeaflet(), $onlineOnly)];
        foreach ($leaflets as $index => $leaflet) {
            $batches[] = new SourceBatch($leaflet['leaflet']->data, $offersByLeaflet[$index]);
        }

        return $batches;
    }

    /**
     * Platné letáky HM a SM s koncovkami ID jejich produktů.
     *
     * @return array<int, array{leaflet: TescoLeaflet, products: array<string, true>}>
     */
    private function leafletsWithProducts(): array
    {
        $leaflets = $this->parser->leaflets($this->postLeaflets(self::LEAFLETS_QUERY, [
            'country' => self::COUNTRY,
            'currentDate' => CarbonImmutable::now()->toIso8601ZuluString(),
        ]));

        $suffixLength = config()->integer('letaky.sources.tesco.leaflet_product_id_suffix_length');

        return array_map(fn (TescoLeaflet $leaflet): array => [
            'leaflet' => $leaflet,
            'products' => $this->parser->leafletProductSuffixes($this->postLeaflets(self::LEAFLET_PRODUCTS_QUERY, [
                'country' => self::COUNTRY,
                'slug' => $leaflet->slug,
                'leafletType' => $leaflet->type,
            ]), $suffixLength),
        ], $leaflets);
    }

    /**
     * Všechny produkty s akcí v e-shopu, po stránkách.
     *
     * @return list<array<string, mixed>>
     */
    private function promotionProducts(string $apiKey): array
    {
        $pageSize = config()->integer('letaky.sources.tesco.eshop_page_size');
        $products = [];
        $page = 1;

        do {
            $response = $this->http->request()
                ->withHeaders(['x-apikey' => $apiKey, 'region' => 'CZ', 'language' => 'cs-CZ'])
                ->post(config()->string('letaky.sources.tesco.eshop_api_url'), [[
                    'query' => self::PROMOTIONS_QUERY,
                    'variables' => ['promotion' => self::ALL_PROMOTIONS, 'page' => $page, 'count' => $pageSize],
                ]])
                ->json();

            $result = $this->parser->promotionsPage(is_array($response) ? $response : []);
            array_push($products, ...$result['products']);
            $page++;
        } while ($result['products'] !== [] && count($products) < $result['total']);

        return $products;
    }

    /**
     * Letáky, ve kterých produkt nabídky je a jejichž platnost se s nabídkou překrývá.
     *
     * @param  array<int, array{leaflet: TescoLeaflet, products: array<string, true>}>  $leaflets
     * @return array<int, true>
     */
    private function matchingLeaflets(OfferData $offer, array $leaflets): array
    {
        $suffix = substr($offer->externalId, -config()->integer('letaky.sources.tesco.leaflet_product_id_suffix_length'));

        $matching = [];
        foreach ($leaflets as $index => $leaflet) {
            $data = $leaflet['leaflet']->data;
            $overlaps = $data->validFrom !== null && $data->validTo !== null
                && $offer->validFrom->lte($data->validTo) && $offer->validTo->gte($data->validFrom);

            if ($overlaps && isset($leaflet['products'][$suffix])) {
                $matching[$index] = true;
            }
        }

        return $matching;
    }

    /**
     * Zdroj „akce e-shopu“ pro nabídky, které nejsou v žádném letáku.
     */
    private function eshopLeaflet(): LeafletData
    {
        return new LeafletData(
            kind: LeafletKind::Eshop,
            externalId: self::ESHOP_LEAFLET_ID,
            sourceUrl: config()->string('letaky.sources.tesco.eshop_promotions_url'),
        );
    }

    /**
     * Dotaz na API letáků (bez autentizace).
     *
     * @param  array<string, mixed>  $variables
     * @return array<mixed>
     */
    private function postLeaflets(string $query, array $variables): array
    {
        $response = $this->http->request()
            ->post(config()->string('letaky.sources.tesco.leaflets_api_url'), ['query' => $query, 'variables' => $variables])
            ->json();

        return is_array($response) ? $response : [];
    }
}
