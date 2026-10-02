<?php

/**
 * Zdroj Albertu — letáky hypermarketů a supermarketů s textem stránek pro zmínky bez ceny
 * (R27, R36, ZDROJE_DAT.md, Albert). Akce s cenou Albert ve strukturované podobě nemá.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Albert;

use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\StoreFormat;

final class AlbertOfferSource implements OfferSource
{
    /** Dotaz na seznam letáků jednoho typu prodejny (ad-hoc, persisted query není potřeba). */
    private const LEAFLETS_QUERY = 'query Leaflets($locationType: String) { getLeaflets(locationType: $locationType, onlyDefault: false) { leaflets { id isDefault title locationType validityStartDate validityEndDate viewUrl } } }';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly AlbertParser $parser,
    ) {}

    /**
     * Albert.
     */
    public function chain(): Chain
    {
        return Chain::Albert;
    }

    /**
     * Dávka za každý hlavní leták hypermarketů a supermarketů — bez nabídek, jen stránky.
     */
    public function fetch(): array
    {
        $batches = [];
        foreach ($this->locationTypes() as $locationType => $format) {
            $response = $this->http->request($this->delay())
                ->post(config()->string('letaky.sources.albert.api_url'), [
                    'query' => self::LEAFLETS_QUERY,
                    'variables' => ['locationType' => $locationType],
                ])
                ->json();

            foreach ($this->parser->leaflets(is_array($response) ? $response : [], $format) as $leaflet) {
                $spreads = $this->http->request($this->delay())
                    ->get($leaflet['viewUrl'].config()->string('letaky.sources.albert.spreads_path'))
                    ->json();

                $batches[] = new SourceBatch($leaflet['leaflet'], offers: [], pages: $this->parser->pages(
                    is_array($spreads) ? $spreads : [],
                    $leaflet['viewUrl'],
                    config()->string('letaky.sources.albert.page_image_base_url'),
                    config()->string('letaky.sources.albert.page_image_size'),
                    config()->string('letaky.sources.albert.page_path'),
                ));
            }
        }

        return $batches;
    }

    /**
     * Typy prodejen z konfigurace: hodnota `locationType` v GraphQL => formát prodejny.
     *
     * @return array<string, StoreFormat>
     */
    private function locationTypes(): array
    {
        $types = [];
        foreach (config()->array('letaky.sources.albert.location_types') as $locationType => $format) {
            $types[(string) $locationType] = StoreFormat::from((string) $format);
        }

        return $types;
    }

    /**
     * Pauza mezi požadavky — leták i GraphQL jsou za Akamai.
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.albert.request_delay_ms');
    }
}
