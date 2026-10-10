<?php

/**
 * Zdroj Albertu — letáky hypermarketů a supermarketů: akce s cenou z PDF letáku (R86)
 * a text stránek pro zmínky bez ceny (R27, R36, ZDROJE_DAT.md, Albert).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Albert;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\LeafletPageData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Data\TileStats;
use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\StoreFormat;
use Illuminate\Http\Client\RequestException;

final class AlbertOfferSource implements OfferSource
{
    /** Dotaz na seznam letáků jednoho typu prodejny (ad-hoc, persisted query není potřeba). */
    private const LEAFLETS_QUERY = 'query Leaflets($locationType: String) { getLeaflets(locationType: $locationType, onlyDefault: false) { leaflets { id isDefault title locationType validityStartDate validityEndDate viewUrl } } }';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly AlbertParser $parser,
        private readonly AlbertLeafletParser $leafletParser,
        private readonly PdfTextReader $pdf,
    ) {}

    /**
     * Albert.
     */
    public function chain(): Chain
    {
        return Chain::Albert;
    }

    /**
     * Dávka za každý hlavní leták hypermarketů a supermarketů — akce z PDF a text stránek.
     * Nepovedené stažení nebo převod PDF ukončí celé stažení chybou: bez akcí jednoho letáku
     * by je import označil jako stažené obchodem (R16).
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    public function fetch(): array
    {
        $leaflets = [];
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

                $pages = $this->parser->pages(
                    is_array($spreads) ? $spreads : [],
                    $leaflet['viewUrl'],
                    config()->string('letaky.sources.albert.page_image_base_url'),
                    config()->string('letaky.sources.albert.page_image_size'),
                    config()->string('letaky.sources.albert.page_path'),
                );

                $offers = $this->leafletOffers($leaflet['leaflet'], $leaflet['viewUrl']);
                // Nalezené a ověřené ceny letáku pro přehled kvality dat (R129)
                $leaflets[] = [$leaflet['leaflet'], $offers, $pages, $this->leafletParser->lastStats()];
            }
        }

        return $this->batches($leaflets);
    }

    /**
     * Akce z PDF letáku: odkaz na PDF je v `data.json` prohlížeče Publitas (`config.downloadPdfUrl`).
     *
     * @return list<OfferData>
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function leafletOffers(LeafletData $leaflet, string $viewUrl): array
    {
        $data = $this->http->request($this->delay())
            ->get($viewUrl.config()->string('letaky.sources.albert.data_path'))
            ->json();
        $pdfUrl = is_array($data) && is_array($data['config'] ?? null) ? ($data['config']['downloadPdfUrl'] ?? null) : null;
        if (! is_string($pdfUrl) || $pdfUrl === '') {
            throw SourceResponseChanged::because(Chain::Albert, "data.json letáku {$leaflet->externalId} bez odkazu na PDF");
        }
        if ($leaflet->validFrom === null || $leaflet->validTo === null) {
            throw SourceResponseChanged::because(Chain::Albert, "leták {$leaflet->externalId} bez platnosti");
        }

        $offers = $this->leafletParser->offers(
            $this->pdf->readUrl($this->http, $pdfUrl, $this->delay()),
            [$leaflet->validFrom, $leaflet->validTo],
            $viewUrl.config()->string('letaky.sources.albert.page_path'),
        );

        return array_map(fn (OfferData $offer): OfferData => $offer->withAvailability(onlineOnly: false, storeFormat: $leaflet->format), $offers);
    }

    /**
     * Dávky letáků. Akce, která je v letáku hypermarketů i supermarketů (stejný název, balení,
     * cena a platnost), platí ve všech prodejnách — bez formátu; uloží ji první dávka, import
     * ji v další přeskočí.
     *
     * @param  list<array{LeafletData, list<OfferData>, list<LeafletPageData>, TileStats}>  $leaflets
     * @return list<SourceBatch>
     */
    private function batches(array $leaflets): array
    {
        $formats = [];
        foreach ($leaflets as [$leaflet, $offers]) {
            foreach ($offers as $offer) {
                $formats[$offer->key()][$leaflet->format?->value] = true;
            }
        }

        return array_map(fn (array $leaflet): SourceBatch => new SourceBatch(
            $leaflet[0],
            array_map(fn (OfferData $offer): OfferData => count($formats[$offer->key()]) > 1 ? $offer->withAvailability(onlineOnly: false, storeFormat: null) : $offer, $leaflet[1]),
            $leaflet[2],
            $leaflet[3],
        ), $leaflets);
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
