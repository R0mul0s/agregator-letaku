<?php

/**
 * Zdroj akční nabídky Billy — product-discovery API se celým katalogem (R48, ZDROJE_DAT.md, Billa)
 * a PDF letáků, které ještě nezačaly (R89).
 *
 * Filtr `inPromotion` nevrátí akce jen s BILLA Klubem (cena jen v `price.loyalty`), proto se
 * prochází celý katalog (~12 tisíc produktů, ~25 stránek po 500) a akce se vyberou v parseru.
 * API nemá platnost akcí: akce platí v akčním týdnu Billy (středa–úterý, jako leták), který
 * obsahuje dnešek. Co obchod v týdnu ukončí dřív, zmizí z API a import ho označí jako
 * stažené (R16) — proto denní stahování.
 *
 * API ukazuje jen dnešní stav, akce dalšího týdne proto z PDF letáků (`BillaLeafletParser`). Dlaždice
 * letáku se spáruje s produktem celého katalogu (`BillaCatalogMatcher`) a dostane jeho kód jako ID —
 * akce platná celý akční týden má tak stejný klíč, jaký jí dá API, až začne, a import řádek jen
 * aktualizuje. Akce s jinou platností (víkend, katalog na tři týdny) má předběžné ID, které akce
 * z API převezme (`OfferData::$supersedes`, R88).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\LoyaltyProgram;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;

final class BillaOfferSource implements OfferSource
{
    /** Předpona externího ID zdroje „akce z webu“ — jeden akční týden. */
    private const LEAFLET_PREFIX = 'web-';

    /** Předpona externího ID zdroje „PDF letáku“ — za ní číslo letáku v Publitas. */
    private const PDF_LEAFLET_PREFIX = 'pdf-';

    /** Číslo letáku v odkazu na PDF (`view.publitas.com/64069/{číslo}/pdfs/…`). */
    private const PUBLITAS_ID_PATTERN = '#/(\d+)/pdfs/#';

    /** Dní v týdnu — akční týden končí den před začátkem dalšího. */
    private const DAYS_IN_WEEK = 7;

    public function __construct(
        private readonly SourceHttp $http,
        private readonly BillaParser $parser,
        private readonly BillaLeafletList $leafletList,
        private readonly BillaLeafletParser $leafletParser,
        private readonly BillaCatalogMatcher $matcher,
        private readonly PdfTextReader $pdf,
        private readonly PackageParser $packages,
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
     * Dávka s akcemi celého katalogu (akční týden) a za každý leták, který ještě nezačal, dávka
     * s akcemi z PDF. Nepovedené stažení nebo převod PDF ukončí celé stažení chybou: bez akcí letáku
     * by je import označil jako stažené obchodem (R16).
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    public function fetch(): array
    {
        [$from, $to] = $this->promotionWeek($this->calendar->today());
        $url = config()->string('letaky.sources.billa.base_url').config()->string('letaky.sources.billa.products_api_path');
        $productUrl = config()->string('letaky.sources.billa.base_url').config()->string('letaky.sources.billa.product_url_path');
        $pageSize = config()->integer('letaky.sources.billa.page_size');

        $offers = [];
        $catalog = [];
        $loaded = 0;
        $page = 0;
        do {
            $response = $this->http->request($this->delay())->get($url, ['page' => $page, 'pageSize' => $pageSize])->json();
            $result = $this->parser->page(is_array($response) ? $response : []);
            foreach ($result['products'] as $product) {
                $offer = $this->parser->offer($product, $from, $to, $productUrl);
                if ($offer !== null) {
                    $offers[$offer->externalId] = $offer;
                }
                $catalogProduct = $this->parser->catalogProduct($product, $productUrl);
                if ($catalogProduct !== null) {
                    $catalog[] = $catalogProduct;
                }
            }
            $loaded += count($result['products']);
            $page++;
        } while ($result['products'] !== [] && $loaded < $result['total']);

        $batches = [new SourceBatch(
            new LeafletData(
                kind: LeafletKind::Web,
                externalId: self::LEAFLET_PREFIX.$from->toDateString(),
                validFrom: $from,
                validTo: $to,
                sourceUrl: config()->string('letaky.sources.billa.offers_page_url'),
            ),
            array_values($offers),
        )];

        return [...$batches, ...$this->leafletBatches($offers, $this->matcher->index($catalog))];
    }

    /**
     * Dávky s akcemi PDF letáků, které ještě nezačaly; leták bez spárované akce má prázdnou
     * dávku se statistikou dlaždic (R129).
     * Akce stejného produktu z více letáků (velký a malý leták) se uloží jednou — z prvního.
     *
     * @param  array<string, OfferData>  $apiOffers  Dnešní akce z API podle kódu produktu
     * @param  array<int, list<BillaCatalogProduct>>  $catalog  Katalog podle běžné ceny
     * @return list<SourceBatch>
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function leafletBatches(array $apiOffers, array $catalog): array
    {
        $batches = [];
        $seen = [];
        $pdfUrls = [];
        foreach ($this->upcomingLeaflets() as $leaflet) {
            $pageUrl = config()->string('letaky.sources.billa.base_url').$leaflet['path'];
            $pdfUrl = $this->leafletList->pdfUrl($this->http->request($this->delay())->get($pageUrl)->body());
            if (isset($pdfUrls[$pdfUrl])) {
                continue;
            }
            $pdfUrls[$pdfUrl] = true;

            $offers = [];
            foreach ($this->leafletItems($leaflet, $pdfUrl) as $item) {
                foreach ($this->matcher->match($item, $catalog) as $product) {
                    if (isset($seen[$product->sku]) || $this->continues($item, $apiOffers[$product->sku] ?? null)) {
                        continue;
                    }
                    $seen[$product->sku] = true;
                    $offers[] = $this->offer($item, $product, $pageUrl);
                }
            }

            // I leták bez nové akce má dávku — jeho nalezené a ověřené ceny patří do přehledu kvality
            // dat (R129), nula ověřených je právě to, co má být vidět
            $batches[] = new SourceBatch(
                new LeafletData(
                    kind: LeafletKind::Leaflet,
                    externalId: self::PDF_LEAFLET_PREFIX.(preg_match(self::PUBLITAS_ID_PATTERN, $pdfUrl, $m) === 1 ? $m[1] : md5($pdfUrl)),
                    title: $leaflet['title'],
                    validFrom: $leaflet['validFrom'],
                    validTo: $leaflet['validTo'],
                    sourceUrl: $pageUrl,
                ),
                $offers,
                tiles: $this->leafletParser->lastStats(),
            );
        }

        return $batches;
    }

    /**
     * Ověřené dlaždice letáku se začátkem po dnešku. Velký leták s podezřele málo ověřenými dlaždicemi
     * = změněné rozvržení nebo rozbitý parser — stažení skončí chybou.
     *
     * @param  array{path: string, title: string, validFrom: CarbonImmutable, validTo: CarbonImmutable}  $leaflet
     * @return list<BillaLeafletItem>
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function leafletItems(array $leaflet, string $pdfUrl): array
    {
        $items = $this->leafletParser->items($this->pdf->readUrl($this->http, $pdfUrl, $this->delay()), [$leaflet['validFrom'], $leaflet['validTo']]);

        $minimum = config()->integer('letaky.sources.billa.pdf_main_min_items');
        if ($leaflet['title'] === config()->string('letaky.sources.billa.pdf_main_title') && count($items) < $minimum) {
            throw SourceResponseChanged::because(Chain::Billa, "leták {$leaflet['title']} od {$leaflet['validFrom']->toDateString()} má jen ".count($items)." ověřených dlaždic z PDF (méně než {$minimum})");
        }

        $today = $this->calendar->today();

        return array_values(array_filter($items, fn (BillaLeafletItem $item): bool => $item->validFrom->greaterThan($today)));
    }

    /**
     * Letáky ze stránky letáků, které ještě nezačaly a mají se stáhnout z PDF: velký a malý leták,
     * leták BILLA klub a katalogy s potravinami (bez speciálů prodejen).
     *
     * @return list<array{path: string, title: string, validFrom: CarbonImmutable, validTo: CarbonImmutable}>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    private function upcomingLeaflets(): array
    {
        $html = $this->http->request($this->delay())->get(config()->string('letaky.sources.billa.offers_page_url'))->body();
        $today = $this->calendar->today();

        return array_values(array_filter($this->leafletList->leaflets($html), function (array $leaflet) use ($today): bool {
            $allowed = false;
            foreach (config()->array('letaky.sources.billa.pdf_leaflet_paths') as $pattern) {
                $allowed = $allowed || preg_match((string) $pattern, $leaflet['path']) === 1;
            }

            return $allowed && $leaflet['validFrom']->greaterThan($today)
                && preg_match(config()->string('letaky.sources.billa.pdf_excluded_titles'), $leaflet['title']) !== 1;
        }));
    }

    /**
     * Pokračuje akce z letáku dnešní akcí z API se stejnou cenou (i s Klubem)? Pak se nezakládá —
     * dnešní řádek prodlouží API, až začne další týden (R54); jinak by akce vznikla dvakrát.
     */
    private function continues(BillaLeafletItem $item, ?OfferData $apiOffer): bool
    {
        return $apiOffer !== null && $apiOffer->price === $item->price && $apiOffer->loyaltyPrice === $item->loyaltyPrice;
    }

    /**
     * Akce z dlaždice pro produkt katalogu: ceny, typ a platnost z letáku, název, balení, kategorie,
     * obrázek a odkaz z katalogu (stejné, jaké dá API po začátku akce). Platí-li přesně akční týden,
     * ID je kód produktu (stejný klíč jako akce z API); jinak předběžné ID, které API převezme.
     */
    private function offer(BillaLeafletItem $item, BillaCatalogProduct $product, string $pageUrl): OfferData
    {
        [$weekFrom, $weekTo] = $this->promotionWeek($item->validFrom);
        $isWeek = $item->validFrom->equalTo($weekFrom) && $item->validTo->equalTo($weekTo);

        return new OfferData(
            externalId: $isWeek ? $product->sku : config()->string('letaky.sources.billa.pdf_provisional_prefix').$product->sku,
            name: $product->name,
            offerType: $item->offerType,
            validFrom: $item->validFrom,
            validTo: $item->validTo,
            raw: ['sku' => $product->sku, 'leafletName' => $item->name, 'leafletPackage' => $item->packageText, ...$item->raw],
            price: $item->price,
            originalPrice: $item->originalPrice,
            loyaltyPrice: $item->loyaltyPrice,
            loyaltyProgram: $item->loyaltyPrice === null ? null : LoyaltyProgram::BillaKlub,
            discountPercent: $item->discountPercent,
            promotionText: $item->promotionText,
            brand: $product->brand,
            packageText: $product->packageText,
            package: $this->packages->parse($product->packageText),
            sourceCategory: $product->category,
            imageUrl: $product->imageUrl,
            sourceUrl: $product->sourceUrl ?? $pageUrl,
        );
    }

    /**
     * Akční týden, který obsahuje den: od posledního dne začátku týdne (středa) do dne před dalším.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function promotionWeek(CarbonImmutable $day): array
    {
        $daysSinceStart = ($day->dayOfWeekIso - config()->integer('letaky.sources.billa.week_start_iso_day') + self::DAYS_IN_WEEK) % self::DAYS_IN_WEEK;
        $from = $day->subDays($daysSinceStart);

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
