<?php

/**
 * Zdroj akční nabídky Lidlu — kampaně týdne na lidl.cz (ZDROJE_DAT.md, Lidl).
 *
 * Z úvodní stránky vezme odkazy na kampaně a stáhne každou z nich; kampaň je zdroj
 * nabídek (leaflets, druh web). Pokrývá zhruba třetinu letáku — zbytek je jen v PDF (R23).
 * Kampaně bez potravin (móda, dílna) se stáhnou, ale nic z nich neuloží.
 * K tomu potravinové letáky jako zdroje bez nabídek, jen s textem stránek pro zmínky bez ceny (R27).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Lidl;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;

final class LidlOfferSource implements OfferSource
{
    public function __construct(
        private readonly SourceHttp $http,
        private readonly LidlParser $parser,
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

        return [...$batches, ...$this->leafletPages($baseUrl)];
    }

    /**
     * Potravinové letáky s textem stránek pro zmínky bez ceny (R27).
     *
     * @return list<SourceBatch>
     */
    private function leafletPages(string $baseUrl): array
    {
        $slugs = $this->parser->leafletSlugs(
            $this->download($baseUrl.config()->string('letaky.sources.lidl.leaflets_page_path')),
            $this->stringList('letaky.sources.lidl.leaflet_slug_prefixes'),
        );

        $batches = [];
        foreach ($slugs as $slug) {
            $response = $this->http->request(config()->integer('letaky.sources.lidl.request_delay_ms'))
                ->get(config()->string('letaky.sources.lidl.flyer_api_url'), ['flyer_identifier' => $slug, 'region_id' => 0, 'region_code' => 0])
                ->json();
            $batches[] = $this->parser->flyer(is_array($response) ? $response : [], $slug, $baseUrl.config()->string('letaky.sources.lidl.flyer_page_path'));
        }

        return $batches;
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
