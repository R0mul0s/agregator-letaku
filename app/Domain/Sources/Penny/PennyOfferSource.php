<?php

/**
 * Zdroj akční nabídky Penny — product-discovery API a vektorová vrstva letáku (R23, ZDROJE_DAT.md).
 *
 * API vrací jen výběr (~33 položek týdně, hlavně s PENNY kartou), leták stovky položek.
 * Položka letáku, kterou už nese API (stejná cena a podobný název), se vynechá — API má
 * přesnější data i cenu s kartou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Penny;

use App\Domain\Matching\TextNormalizer;
use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\LeafletPageData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Carbon\CarbonImmutable;

final class PennyOfferSource implements OfferSource
{
    /** Odkaz na leták na stránce letáků: …/PennyIntLeaflet/CZ/30_09_2026/. */
    private const LEAFLET_LINK_PATTERN = '#PennyIntLeaflet/CZ/(\d{2}_\d{2}_\d{4})/#';

    /** Odkazy na stránky v indexu letáku: href="./37/". */
    private const PAGE_LINK_PATTERN = '#href="\./(\d+)/"#';

    /** Předpona externího ID zdroje „akce z webu“ (API) — jeden týden. */
    private const API_LEAFLET_PREFIX = 'web-';

    /** Kolik slov názvu musí položka letáku sdílet s položkou API, aby šlo o tutéž věc. */
    private const SAME_PRODUCT_MIN_SHARED_WORDS = 1;

    /** Nejkratší slovo, které se při porovnání názvů počítá (ne „s“, „a“, „z“). */
    private const SAME_PRODUCT_MIN_WORD_LENGTH = 3;

    public function __construct(
        private readonly SourceHttp $http,
        private readonly PennyApiParser $api,
        private readonly SvgTextReader $svg,
        private readonly PennyLeafletParser $leaflet,
        private readonly TextNormalizer $normalizer,
    ) {}

    /**
     * Penny.
     */
    public function chain(): Chain
    {
        return Chain::Penny;
    }

    /**
     * Dávka akcí z API a dávka za každý zveřejněný leták (aktuální, případně příští).
     */
    public function fetch(): array
    {
        $apiOffers = $this->apiOffers();
        $batches = $apiOffers === [] ? [] : [new SourceBatch($this->apiLeaflet($apiOffers), $apiOffers)];

        foreach ($this->leafletFolders() as $folder) {
            [$leaflet, $offers, $pages] = $this->leafletOffers($folder);
            $batches[] = new SourceBatch($leaflet, array_values(array_filter(
                $offers,
                fn (OfferData $offer): bool => ! $this->isInApi($offer, $apiOffers),
            )), $pages);
        }

        return $batches;
    }

    /**
     * Všechny akce z API, po stránkách.
     *
     * @return list<OfferData>
     */
    private function apiOffers(): array
    {
        $url = config()->string('letaky.sources.penny.base_url').config()->string('letaky.sources.penny.products_api_path');
        $productUrl = config()->string('letaky.sources.penny.base_url').config()->string('letaky.sources.penny.product_url_path');
        $pageSize = config()->integer('letaky.sources.penny.page_size');

        $offers = [];
        $loaded = 0;
        $page = 0;
        do {
            $response = $this->http->request($this->delay())->get($url, ['page' => $page, 'pageSize' => $pageSize])->json();
            $result = $this->api->page(is_array($response) ? $response : []);
            foreach ($result['products'] as $product) {
                $offer = $this->api->offer($product, $productUrl);
                if ($offer !== null) {
                    $offers[] = $offer;
                }
            }
            $loaded += count($result['products']);
            $page++;
        } while ($result['products'] !== [] && $loaded < $result['total']);

        return $offers;
    }

    /**
     * Zdroj „akce z webu“ s platností od první do poslední akce.
     *
     * @param  non-empty-list<OfferData>  $offers
     */
    private function apiLeaflet(array $offers): LeafletData
    {
        [$from, $to] = $this->range($offers);

        return new LeafletData(
            kind: LeafletKind::Web,
            externalId: self::API_LEAFLET_PREFIX.$from->toDateString(),
            validFrom: $from,
            validTo: $to,
            sourceUrl: config()->string('letaky.sources.penny.base_url').config()->string('letaky.sources.penny.leaflets_page_path'),
        );
    }

    /**
     * Složky zveřejněných letáků („30_09_2026“) ze stránky letáků.
     *
     * @return list<string>
     *
     * @throws SourceResponseChanged
     */
    private function leafletFolders(): array
    {
        $html = $this->http->request($this->delay())
            ->get(config()->string('letaky.sources.penny.base_url').config()->string('letaky.sources.penny.leaflets_page_path'))
            ->body();

        preg_match_all(self::LEAFLET_LINK_PATTERN, $html, $matches);
        $folders = array_values(array_unique($matches[1]));

        return $folders === [] ? throw SourceResponseChanged::because(Chain::Penny, 'stránka letáků neodkazuje na žádný leták') : $folders;
    }

    /**
     * Leták, jeho ověřené akce ze všech stránek a text stránek pro zmínky bez ceny (R27).
     *
     * @return array{LeafletData, list<OfferData>, list<LeafletPageData>}
     *
     * @throws SourceResponseChanged
     */
    private function leafletOffers(string $folder): array
    {
        $baseUrl = config()->string('letaky.sources.penny.leaflet_base_url').$folder.'/';
        preg_match_all(self::PAGE_LINK_PATTERN, $this->http->request($this->delay())->get($baseUrl)->body(), $matches);
        $pageCount = $matches[1] === [] ? 0 : max(array_map(intval(...), $matches[1]));
        if ($pageCount === 0) {
            throw SourceResponseChanged::because(Chain::Penny, "leták {$folder} nemá odkazy na stránky");
        }

        // Stránka čistě z obrázku textovou vrstvu nemá (404) — nemá ani akce k vytěžení
        $pages = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $svgUrl = $baseUrl.sprintf(config()->string('letaky.sources.penny.leaflet_page_svg_path'), $page);
            $response = $this->http->request($this->delay(), allowNotFound: true)->get($svgUrl);
            if ($response->successful()) {
                $pages[$page] = $this->svg->tokens($response->body());
            }
        }

        // Platnost letáku = „Nabídka platná od … do …“ z první stránky, která ji uvádí
        $default = null;
        foreach ($pages as $tokens) {
            $default ??= $this->leaflet->pageValidity($tokens);
        }
        if ($default === null) {
            throw SourceResponseChanged::because(Chain::Penny, "leták {$folder} neuvádí platnost");
        }

        $offers = [];
        $pageTexts = [];
        foreach ($pages as $page => $tokens) {
            $validity = $this->leaflet->pageValidity($tokens) ?? $default;
            array_push($offers, ...$this->leaflet->offers($tokens, $validity, $page, $baseUrl.$page.'/'));
            $text = $this->leaflet->pageText($tokens);
            if ($text !== null) {
                $pageTexts[] = new LeafletPageData(number: $page, text: $text, pageUrl: $baseUrl.$page.'/');
            }
        }

        return [
            new LeafletData(kind: LeafletKind::Leaflet, externalId: $folder, validFrom: $default[0], validTo: $default[1], sourceUrl: $baseUrl),
            $offers,
            $pageTexts,
        ];
    }

    /**
     * Nese položku letáku už API? Stejná cena (bez karty nebo s ní), stejné balení a společné
     * slovo názvu. Samotná slova nestačí — značka („Karlova Koruna“) je u desítek položek.
     *
     * @param  list<OfferData>  $apiOffers
     */
    private function isInApi(OfferData $offer, array $apiOffers): bool
    {
        $words = $this->words($offer->name);
        foreach ($apiOffers as $apiOffer) {
            if (in_array($offer->price, [$apiOffer->price, $apiOffer->loyaltyPrice], true)
                && $offer->package !== null && $offer->package == $apiOffer->package
                && count(array_intersect($words, $this->words($apiOffer->name))) >= self::SAME_PRODUCT_MIN_SHARED_WORDS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalizovaná slova názvu pro porovnání.
     *
     * @return list<string>
     */
    private function words(string $name): array
    {
        return array_values(array_filter(
            explode(' ', trim($this->normalizer->normalize($name))),
            fn (string $word): bool => mb_strlen($word) >= self::SAME_PRODUCT_MIN_WORD_LENGTH,
        ));
    }

    /**
     * Nejdřívější začátek a nejpozdější konec platnosti nabídek.
     *
     * @param  non-empty-list<OfferData>  $offers
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function range(array $offers): array
    {
        $from = $offers[0]->validFrom;
        $to = $offers[0]->validTo;
        foreach ($offers as $offer) {
            $from = $from->min($offer->validFrom);
            $to = $to->max($offer->validTo);
        }

        return [$from, $to];
    }

    /**
     * Pauza mezi požadavky — leták má desítky malých stránek.
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.penny.request_delay_ms');
    }
}
