<?php

/**
 * Zdroj akční nabídky Lidlu — kampaně týdne na lidl.cz (ZDROJE_DAT.md, Lidl).
 *
 * Z úvodní stránky vezme odkazy na kampaně a stáhne každou z nich; kampaň je zdroj
 * nabídek (leaflets, druh web). Pokrývá zhruba třetinu letáku — zbytek je jen v PDF (R23).
 * Kampaně bez potravin (móda, dílna) se stáhnou, ale nic z nich neuloží.
 * K tomu potravinové letáky s textem stránek pro zmínky bez ceny (R27) a s akcemi z PDF letáku,
 * které ověří cena za jednotku (R86); akce, kterou už nese web, se z letáku nezdvojí.
 * Chyba stažení nebo převodu PDF ukončí celé stažení — tichý výpadek by akce z letáku označil
 * jako stažené obchodem (R16).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Lidl;

use App\Domain\Matching\TextNormalizer;
use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;

final class LidlOfferSource implements OfferSource
{
    /** Nejkratší výrazné slovo názvu pro porovnání akce z letáku s akcí z webu (bez „10“, „a“, „s“). */
    private const SIGNIFICANT_WORD_MIN_LENGTH = 4;

    public function __construct(
        private readonly SourceHttp $http,
        private readonly LidlParser $parser,
        private readonly LidlLeafletParser $leafletParser,
        private readonly PdfTextReader $pdfReader,
        private readonly TextNormalizer $normalizer,
    ) {}

    /**
     * Lidl.
     */
    public function chain(): Chain
    {
        return Chain::Lidl;
    }

    /**
     * Dávka za každou kampaň s aspoň jednou nabídkou.
     */
    public function fetch(): array
    {
        $baseUrl = config()->string('letaky.sources.lidl.base_url');
        $campaigns = $this->parser->campaigns(
            $this->download($baseUrl.config()->string('letaky.sources.lidl.campaign_index_path')),
            $this->stringList('letaky.sources.lidl.excluded_campaigns'),
        );
        $categories = $this->stringList('letaky.sources.lidl.categories');

        $batches = [];
        foreach ($campaigns as $path => $campaignId) {
            $offers = $this->parser->offers($this->download($baseUrl.$path), $categories, $baseUrl);
            if ($offers !== []) {
                $batches[] = new SourceBatch($this->leaflet($campaignId, $baseUrl.$path, $offers), $offers);
            }
        }

        $webOffers = array_merge(...array_map(fn (SourceBatch $batch): array => $batch->offers, $batches));

        return [...$batches, ...$this->leaflets($baseUrl, $webOffers)];
    }

    /**
     * Potravinové letáky: text stránek pro zmínky bez ceny (R27) a ověřené akce z PDF (R86)
     * kromě těch, které už nese web.
     *
     * @param  list<OfferData>  $webOffers
     * @return list<SourceBatch>
     *
     * @throws PdfTextFailed
     */
    private function leaflets(string $baseUrl, array $webOffers): array
    {
        $slugs = $this->parser->leafletSlugs(
            $this->download($baseUrl.config()->string('letaky.sources.lidl.leaflets_page_path')),
            $this->stringList('letaky.sources.lidl.leaflet_slug_prefixes'),
        );
        $pageUrlPattern = $baseUrl.config()->string('letaky.sources.lidl.flyer_page_path');

        $batches = [];
        foreach ($slugs as $slug) {
            $response = $this->http->request(config()->integer('letaky.sources.lidl.request_delay_ms'))
                ->get(config()->string('letaky.sources.lidl.flyer_api_url'), ['flyer_identifier' => $slug, 'region_id' => 0, 'region_code' => 0])
                ->json();
            $response = is_array($response) ? $response : [];
            $flyer = $this->parser->flyer($response, $slug, $pageUrlPattern);

            $pages = $this->pdfReader->read($this->downloadPdf($this->parser->pdfUrl($response, $slug)));
            $offers = array_values(array_filter(
                $this->leafletParser->offers($pages, $flyer->leaflet, $slug, $pageUrlPattern),
                fn (OfferData $offer): bool => ! array_any($webOffers, fn (OfferData $webOffer): bool => $this->isSameOffer($offer, $webOffer)),
            ));

            $batches[] = new SourceBatch($flyer->leaflet, $offers, $flyer->pages);
        }

        return $batches;
    }

    /**
     * Je akce z letáku táž jako akce z webu (CLAUDE.md bod 7)? Stejná cena (bez aplikace nebo s ní),
     * překrývající se platnost a výrazná slova kratšího názvu jsou všechna v delším („Mango“ =
     * „Mango zralé“). Jedno společné slovo nestačí — bývá to značka nebo druh („PIKOK Kladenská
     * pečeně“ a „PIKOK PURE Dušená šunka“ za 19,90). Web má přednost — má ID produktu a obrázek.
     */
    private function isSameOffer(OfferData $leafletOffer, OfferData $webOffer): bool
    {
        $price = $leafletOffer->loyaltyPrice ?? $leafletOffer->price;
        if ($price === null || ! in_array($price, [$webOffer->price, $webOffer->loyaltyPrice], true)
            || $leafletOffer->validFrom->gt($webOffer->validTo) || $webOffer->validFrom->gt($leafletOffer->validTo)) {
            return false;
        }

        $leafletWords = $this->significantWords($leafletOffer->name);
        $webWords = $this->significantWords($webOffer->name);
        [$shorter, $longer] = count($leafletWords) <= count($webWords) ? [$leafletWords, $webWords] : [$webWords, $leafletWords];

        return $shorter !== [] && array_diff($shorter, $longer) === [];
    }

    /**
     * Výrazná slova názvu — normalizovaná, aspoň SIGNIFICANT_WORD_MIN_LENGTH znaků, ne čísla.
     *
     * @return list<string>
     */
    private function significantWords(string $name): array
    {
        return array_values(array_filter(
            explode(' ', trim($this->normalizer->normalize($name))),
            fn (string $word): bool => mb_strlen($word) >= self::SIGNIFICANT_WORD_MIN_LENGTH && ! is_numeric($word),
        ));
    }

    /**
     * PDF letáku (25–35 MB) s delším timeoutem; chyba stažení vyhodí výjimku.
     */
    private function downloadPdf(string $url): string
    {
        return $this->http->request(config()->integer('letaky.sources.lidl.request_delay_ms'))
            ->timeout(config()->integer('letaky.http.pdf_timeout_seconds'))
            ->get($url)
            ->body();
    }

    /**
     * Kampaň jako zdroj nabídek s platností od první do poslední akce.
     *
     * @param  non-empty-list<OfferData>  $offers
     */
    private function leaflet(string $campaignId, string $url, array $offers): LeafletData
    {
        $from = $offers[0]->validFrom;
        $to = $offers[0]->validTo;
        foreach ($offers as $offer) {
            $from = $from->min($offer->validFrom);
            $to = $to->max($offer->validTo);
        }

        return new LeafletData(
            kind: LeafletKind::Web,
            externalId: $campaignId,
            validFrom: $from,
            validTo: $to,
            sourceUrl: $url,
        );
    }

    /**
     * Seznam textů z konfigurace.
     *
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        return array_values(array_map(strval(...), config()->array($key)));
    }

    /**
     * HTML stránky s pauzou pro Lidl.
     */
    private function download(string $url): string
    {
        return $this->http->request(config()->integer('letaky.sources.lidl.request_delay_ms'))->get($url)->body();
    }
}
