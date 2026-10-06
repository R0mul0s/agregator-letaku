<?php

/**
 * Zdroj akční nabídky Globusu — veřejné REST API webu (R46) a PDF budoucích letáků (R88,
 * ZDROJE_DAT.md, Globus).
 *
 * Akce s cenou a platností jsou v katalogu akcí jednoho hypermarketu; položky letáku
 * se stahují kvůli popisu („různé druhy“) a názvu, cenu nesou ty z katalogu. Akce mají
 * různou platnost (týden, dva týdny, měsíc), proto jsou všechny v jednom průběžném
 * zdroji „akční nabídka“ jako akce e-shopu Tesca.
 *
 * API vrací jen akce, které už platí. Akce letáků, které ještě nezačaly, se proto berou z PDF
 * (`GlobusLeafletParser`) — každý leták je vlastní zdroj. Až akce začne, vrátí ji API a import
 * převezme řádek z letáku (`OfferData::$supersedes`), takže se neohlásí podruhé jako nová.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Globus;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;

final class GlobusOfferSource implements OfferSource
{
    /** Externí ID průběžného zdroje akcí (jediný, akce si nesou vlastní platnost). */
    private const LEAFLET_ID = 'akcni-nabidka';

    /** ID tematického letáku je ID hlavního letáku s příponou („6abe…47-2“). */
    private const THEME_ID_SEPARATOR = '-';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly GlobusParser $parser,
        private readonly GlobusLeafletParser $leafletParser,
        private readonly PdfTextReader $pdf,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Globus.
     */
    public function chain(): Chain
    {
        return Chain::Globus;
    }

    /**
     * Dávka se všemi akcemi katalogu a za každý leták, který ještě nezačal, dávka s akcemi z PDF.
     * Nepovedené stažení nebo převod PDF ukončí celé stažení chybou: bez akcí letáku by je import
     * označil jako stažené obchodem (R16).
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    public function fetch(): array
    {
        $items = $this->leafletItems();
        $descriptions = $this->parser->descriptionsByEan($items);
        $names = $this->parser->leafletNamesByEan($items);

        $offers = [];
        foreach ($this->catalogProducts() as $product) {
            $offer = $this->parser->offer($product, $descriptions, $names);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        $batches = [new SourceBatch(
            new LeafletData(
                kind: LeafletKind::Web,
                externalId: self::LEAFLET_ID,
                sourceUrl: config()->string('letaky.sources.globus.offers_page_url'),
            ),
            $offers,
        )];

        foreach ($this->upcomingLeaflets() as $leaflet) {
            $batches[] = new SourceBatch(
                new LeafletData(
                    kind: LeafletKind::Leaflet,
                    externalId: $leaflet['id'],
                    title: $leaflet['name'],
                    validFrom: $leaflet['validFrom'],
                    validTo: $leaflet['validTo'],
                    sourceUrl: config()->string('letaky.sources.globus.offers_page_url'),
                ),
                $this->leafletOffers($leaflet, $offers),
            );
        }

        return $batches;
    }

    /**
     * Akce z PDF letáku, které ještě nezačaly; akci, kterou už vrací API (stejný otisk názvu
     * a ceny, překrývající se platnost), vynechá — tu nese dávka katalogu.
     *
     * @param  array{id: string, name: string, type: string, validFrom: CarbonImmutable, validTo: CarbonImmutable, pdfUrl: string}  $leaflet
     * @param  list<OfferData>  $apiOffers
     * @return list<OfferData>
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function leafletOffers(array $leaflet, array $apiOffers): array
    {
        // PDF má ~38 MB — delší časový limit než běžná odpověď
        $pdf = $this->http->request()
            ->timeout(config()->integer('letaky.http.pdf_timeout_seconds'))
            ->get($leaflet['pdfUrl'])
            ->body();

        $offers = $this->leafletParser->offers(
            $this->pdf->read($pdf),
            [$leaflet['validFrom'], $leaflet['validTo']],
            config()->string('letaky.sources.globus.offers_page_url'),
        );

        $minimum = config()->integer('letaky.sources.globus.pdf_main_min_offers');
        if ($leaflet['type'] === config()->string('letaky.sources.globus.pdf_main_type') && count($offers) < $minimum) {
            throw SourceResponseChanged::because(Chain::Globus, "leták {$leaflet['name']} má jen ".count($offers)." ověřených akcí z PDF (méně než {$minimum})");
        }

        $claimed = [];
        foreach ($apiOffers as $offer) {
            if ($offer->supersedes !== null) {
                $claimed[$offer->supersedes][] = [$offer->validFrom, $offer->validTo];
            }
        }

        $today = $this->calendar->today();

        return array_values(array_filter($offers, function (OfferData $offer) use ($today, $claimed): bool {
            if (! $offer->validFrom->greaterThan($today)) {
                return false;
            }
            foreach ($claimed[$offer->externalId] ?? [] as [$from, $to]) {
                if ($from->lessThanOrEqualTo($offer->validTo) && $to->greaterThanOrEqualTo($offer->validFrom)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Letáky a katalogy, které ještě nezačaly a mají se stáhnout z PDF: hlavní leták, tematický
     * leták bez hlavního letáku v seznamu a katalog s potravinami, drogerií nebo krmivy.
     *
     * @return list<array{id: string, name: string, type: string, validFrom: CarbonImmutable, validTo: CarbonImmutable, pdfUrl: string}>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    private function upcomingLeaflets(): array
    {
        $pageSize = config()->integer('letaky.sources.globus.leaflets_page_size');
        $leaflets = [];
        $page = 0;
        do {
            $pageLeaflets = $this->parser->leafletsPage($this->get('leaflets_path', $page, $pageSize));
            array_push($leaflets, ...$pageLeaflets);
            $page++;
        } while (count($pageLeaflets) === $pageSize);

        $mainIds = [];
        foreach ($leaflets as $leaflet) {
            if ($leaflet['type'] === config()->string('letaky.sources.globus.pdf_main_type')) {
                $mainIds[$leaflet['id']] = true;
            }
        }

        $today = $this->calendar->today();

        return array_values(array_filter($leaflets, fn (array $leaflet): bool => $leaflet['validFrom']->greaterThan($today)
            && in_array($leaflet['type'], config()->array('letaky.sources.globus.pdf_leaflet_types'), true)
            && match ($leaflet['type']) {
                config()->string('letaky.sources.globus.pdf_theme_type') => ! isset($mainIds[strstr($leaflet['id'], self::THEME_ID_SEPARATOR, true) ?: $leaflet['id']]),
                config()->string('letaky.sources.globus.pdf_catalogue_type') => preg_match(config()->string('letaky.sources.globus.pdf_catalogue_pattern'), $leaflet['name']) === 1,
                default => true,
            }));
    }

    /**
     * Všechny položky katalogu akcí. Stránkuje se podle `paginationShowMore` — `totalCount` nesedí.
     *
     * @return list<array<string, mixed>>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
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
     *
     * @throws SourceResponseChanged
     * @throws RequestException
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
     * @param  int|null  $pageSize  Velikost stránky; null = `page_size` zdroje
     * @return array<mixed>
     *
     * @throws RequestException
     */
    private function get(string $pathKey, int $page, ?int $pageSize = null): array
    {
        $url = sprintf(config()->string('letaky.sources.globus.api_url'), config()->integer('letaky.sources.globus.house_id'))
            .config()->string("letaky.sources.globus.{$pathKey}");
        $response = $this->http->request()
            ->get($url, ['page' => $page, 'pageSize' => $pageSize ?? config()->integer('letaky.sources.globus.page_size')])
            ->json();

        return is_array($response) ? $response : [];
    }
}
