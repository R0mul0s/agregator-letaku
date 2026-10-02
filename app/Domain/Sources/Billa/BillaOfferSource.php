<?php

/**
 * Zdroj akční nabídky Billy — product-discovery API se celým katalogem (R48, ZDROJE_DAT.md, Billa).
 *
 * Filtr `inPromotion` nevrátí akce jen s BILLA Klubem (cena jen v `price.loyalty`), proto se
 * prochází celý katalog (~12 tisíc produktů, ~25 stránek po 500) a akce se vyberou v parseru.
 * API nemá platnost akcí: akce platí v akčním týdnu Billy (středa–úterý, jako leták), který
 * obsahuje dnešek. Co obchod v týdnu ukončí dřív, zmizí z API a import ho označí jako
 * stažené (R16) — proto denní stahování.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Carbon\CarbonImmutable;

final class BillaOfferSource implements OfferSource
{
    /** Předpona externího ID zdroje „akce z webu“ — jeden akční týden. */
    private const LEAFLET_PREFIX = 'web-';

    /** Dní v týdnu — akční týden končí den před začátkem dalšího. */
    private const DAYS_IN_WEEK = 7;

    public function __construct(
        private readonly SourceHttp $http,
        private readonly BillaParser $parser,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Billa.
     */
    public function chain(): Chain
    {
        return Chain::Billa;
    }

    /**
     * Jedna dávka: akce celého katalogu s platností akčního týdne.
     */
    public function fetch(): array
    {
        [$from, $to] = $this->promotionWeek();
        $url = config()->string('letaky.sources.billa.base_url').config()->string('letaky.sources.billa.products_api_path');
        $productUrl = config()->string('letaky.sources.billa.base_url').config()->string('letaky.sources.billa.product_url_path');
        $pageSize = config()->integer('letaky.sources.billa.page_size');

        $offers = [];
        $loaded = 0;
        $page = 0;
        do {
            $response = $this->http->request($this->delay())->get($url, ['page' => $page, 'pageSize' => $pageSize])->json();
            $result = $this->parser->page(is_array($response) ? $response : []);
            foreach ($result['products'] as $product) {
                $offer = $this->parser->offer($product, $from, $to, $productUrl);
                if ($offer !== null) {
                    $offers[] = $offer;
                }
            }
            $loaded += count($result['products']);
            $page++;
        } while ($result['products'] !== [] && $loaded < $result['total']);

        return [new SourceBatch(
            new LeafletData(
                kind: LeafletKind::Web,
                externalId: self::LEAFLET_PREFIX.$from->toDateString(),
                validFrom: $from,
                validTo: $to,
                sourceUrl: config()->string('letaky.sources.billa.offers_page_url'),
            ),
            $offers,
        )];
    }

    /**
     * Akční týden, který obsahuje dnešek: od posledního dne začátku týdne (středa) do dne před dalším.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function promotionWeek(): array
    {
        $today = $this->calendar->today();
        $daysSinceStart = ($today->dayOfWeekIso - config()->integer('letaky.sources.billa.week_start_iso_day') + self::DAYS_IN_WEEK) % self::DAYS_IN_WEEK;
        $from = $today->subDays($daysSinceStart);

        return [$from, $from->addDays(self::DAYS_IN_WEEK - 1)];
    }

    /**
     * Pauza mezi požadavky — stránky katalogu jsou velké (~1 MB).
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.billa.request_delay_ms');
    }
}
