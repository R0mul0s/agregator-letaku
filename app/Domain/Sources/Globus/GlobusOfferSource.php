<?php

/**
 * Zdroj akční nabídky Globusu — veřejné REST API webu (R46) a PDF budoucích letáků (R88,
 * ZDROJE_DAT.md, Globus), po hypermarketech (R131).
 *
 * Akce s cenou a platností jsou v katalogu akcí každého hypermarketu; katalogy se stahují ze
 * všech hypermarketů cenových pásem (`price_zones`) souběžně a slučují po položce a ceně
 * (`GlobusHouseOffers`) — akce, která neplatí všude nebo má v části hypermarketů jinou cenu,
 * dostane prodejny (R49). Položky letáku se stahují kvůli popisu („různé druhy“) a názvu, cenu
 * nesou ty z katalogu. Akce mají různou platnost (týden, dva týdny, měsíc), proto jsou všechny
 * v jednom průběžném zdroji „akční nabídka“ jako akce e-shopu Tesca.
 *
 * API vrací jen akce, které už platí. Akce letáků, které ještě nezačaly, se proto berou z PDF
 * (`GlobusLeafletParser`) — každý leták je vlastní zdroj. PDF má každý hypermarket vlastní (jiné
 * ceny po pásmech, jiné pultové zboží); stahuje se jedno za cenové pásmo a jeho akce platí
 * v hypermarketech pásma. Až akce začne, vrátí ji API a import převezme řádek z letáku
 * (`OfferData::$supersedes`), takže se neohlásí podruhé jako nová.
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
use App\Domain\Offers\Data\TileStats;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

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
     * Dávka se všemi akcemi katalogů a za každý leták, který ještě nezačal, dávka s akcemi z PDF.
     * Nepovedené stažení katalogu některého hypermarketu nebo stažení či převod PDF ukončí celé
     * stažení chybou: bez nich by import akce označil jako stažené obchodem (R16) nebo je přiřadil
     * špatným prodejnám.
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     * @throws ConnectionException
     */
    public function fetch(): array
    {
        $zones = $this->priceZones();
        $houses = array_values(array_unique(array_merge(...array_column($zones, 'houses'))));
        $representatives = array_column($zones, 'representative');

        $items = $this->leafletItems($representatives);
        $offers = $this->catalogOffers($houses, $this->parser->descriptionsByEan($items), $this->parser->leafletNamesByEan($items));
        unset($items);

        $batches = [new SourceBatch(
            new LeafletData(
                kind: LeafletKind::Web,
                externalId: self::LEAFLET_ID,
                sourceUrl: config()->string('letaky.sources.globus.offers_page_url'),
            ),
            $offers,
        )];

        foreach ($this->upcomingLeaflets($zones) as $leaflet) {
            [$leafletOffers, $tiles] = $this->leafletOffers($leaflet, $houses, $offers);
            $batches[] = new SourceBatch(
                new LeafletData(
                    kind: LeafletKind::Leaflet,
                    externalId: $leaflet['id'],
                    title: $leaflet['name'],
                    validFrom: $leaflet['validFrom'],
                    validTo: $leaflet['validTo'],
                    sourceUrl: config()->string('letaky.sources.globus.offers_page_url'),
                ),
                $leafletOffers,
                // Nalezené a ověřené ceny letáku pro přehled kvality dat (R129) — z PDF výchozího pásma
                tiles: $tiles,
            );
        }

        return $batches;
    }

    /**
     * Cenová pásma: hypermarket, jehož PDF letáku platí pro pásmo, a hypermarkety pásma. Pásmo
     * s výchozím hypermarketem je první a výchozí hypermarket v něm taky (jeho položky letáku,
     * PDF a údaje akcí mají přednost); bez pásem v konfiguraci jen výchozí hypermarket.
     *
     * @return non-empty-list<array{representative: string, houses: non-empty-list<string>}>
     */
    private function priceZones(): array
    {
        $reference = (string) config()->integer('letaky.sources.globus.house_id');
        $zones = [];
        foreach (config()->array('letaky.sources.globus.price_zones') as $representative => $houses) {
            $houses = array_values(array_map(strval(...), is_array($houses) ? $houses : []));
            if (in_array($reference, $houses, true)) {
                array_unshift($zones, ['representative' => (string) $representative, 'houses' => [$reference, ...array_values(array_diff($houses, [$reference]))]]);
            } elseif ($houses !== []) {
                $zones[] = ['representative' => (string) $representative, 'houses' => $houses];
            }
        }

        return $zones === [] ? [['representative' => $reference, 'houses' => [$reference]]] : $zones;
    }

    /**
     * Akce z katalogů hypermarketů sloučené po položce a ceně (`GlobusHouseOffers`). Katalogy se
     * stahují po stránkách souběžně; stránkuje se podle `paginationShowMore` — `totalCount` nesedí.
     *
     * @param  list<string>  $houses  Hypermarkety, výchozí první
     * @param  array<string, string>  $descriptions  Popisy z letáku podle EAN
     * @param  array<string, string>  $names  Názvy položek letáku podle EAN
     * @return list<OfferData>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     * @throws ConnectionException
     */
    private function catalogOffers(array $houses, array $descriptions, array $names): array
    {
        $merged = new GlobusHouseOffers($houses[0]);
        $pending = $houses;
        $page = 0;

        while ($pending !== []) {
            $responses = $this->pages($pending, 'catalog_path', $page);
            $next = [];
            foreach ($pending as $house) {
                $result = $this->parser->catalogPage($this->json($responses[$house]));
                unset($responses[$house]);
                foreach ($result['products'] as $product) {
                    $offer = $this->parser->offer($product, $descriptions, $names);
                    if ($offer !== null) {
                        $merged->add($house, $offer);
                    }
                }
                if ($result['hasMore'] && $result['products'] !== []) {
                    $next[] = $house;
                }
            }
            $pending = $next;
            $page++;
        }

        return $merged->offers($houses);
    }

    /**
     * Položky letáků hypermarketů (zástupců pásem); položky prvního jsou první — u stejného EAN
     * vyhrává jeho popis. Poslední stránka je kratší než plná.
     *
     * @param  list<string>  $houses
     * @return list<array<string, mixed>>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     * @throws ConnectionException
     */
    private function leafletItems(array $houses): array
    {
        $pageSize = config()->integer('letaky.sources.globus.page_size');
        $items = array_fill_keys($houses, []);
        $pending = $houses;
        $page = 0;

        while ($pending !== []) {
            $responses = $this->pages($pending, 'leaflet_items_path', $page);
            $next = [];
            foreach ($pending as $house) {
                $pageItems = $this->parser->leafletItemsPage($this->json($responses[$house]));
                array_push($items[$house], ...$pageItems);
                if (count($pageItems) === $pageSize) {
                    $next[] = $house;
                }
            }
            $pending = $next;
            $page++;
        }

        return array_merge(...array_values($items));
    }

    /**
     * Akce z PDF letáku za každé cenové pásmo, které leták má. Akce stejná ve více pásmech (stejný
     * otisk názvu a ceny i platnost) je jedna a platí v hypermarketech těch pásem. Vynechá akce,
     * které už platí, a akce, které už vrací API (stejný otisk, překrývající se platnost) — ty
     * nese dávka katalogu.
     *
     * @param  array{id: string, name: string, type: string, validFrom: CarbonImmutable, validTo: CarbonImmutable, pdfs: list<array{url: string, representative: string, houses: list<string>}>}  $leaflet
     * @param  list<string>  $houses  Všechny hypermarkety
     * @param  list<OfferData>  $apiOffers
     * @return array{list<OfferData>, TileStats|null} Akce a statistika PDF prvního pásma
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function leafletOffers(array $leaflet, array $houses, array $apiOffers): array
    {
        $minimum = config()->integer('letaky.sources.globus.pdf_main_min_offers');
        $isMain = $leaflet['type'] === config()->string('letaky.sources.globus.pdf_main_type');
        $found = [];
        $tiles = null;

        foreach ($leaflet['pdfs'] as $pdf) {
            $offers = $this->leafletParser->offers(
                $this->pdf->readUrl($this->http, $pdf['url']),
                [$leaflet['validFrom'], $leaflet['validTo']],
                config()->string('letaky.sources.globus.offers_page_url'),
            );
            $tiles ??= $this->leafletParser->lastStats();

            if ($isMain && count($offers) < $minimum) {
                throw SourceResponseChanged::because(Chain::Globus, "leták {$leaflet['name']} (hypermarket {$pdf['representative']}) má jen ".count($offers)." ověřených akcí z PDF (méně než {$minimum})");
            }

            foreach ($offers as $offer) {
                $found[$offer->key()]['offer'] ??= $offer;
                $found[$offer->key()]['houses'] = [...$found[$offer->key()]['houses'] ?? [], ...$pdf['houses']];
            }
        }

        $claimed = [];
        foreach ($apiOffers as $offer) {
            if ($offer->supersedes !== null) {
                $claimed[$offer->supersedes][] = [$offer->validFrom, $offer->validTo];
            }
        }

        $today = $this->calendar->today();
        $result = [];
        foreach ($found as ['offer' => $offer, 'houses' => $offerHouses]) {
            if (! $offer->validFrom->greaterThan($today) || $this->isClaimed($offer, $claimed)) {
                continue;
            }
            $codes = array_values(array_unique($offerHouses));
            sort($codes);
            $result[] = $offer->withStoreCodes(count($codes) === count($houses) ? null : $codes);
        }

        return [$result, $tiles];
    }

    /**
     * Vrací akci z PDF už API (stejný otisk a překrývající se platnost)?
     *
     * @param  array<string, list<array{CarbonImmutable, CarbonImmutable}>>  $claimed  Otisk => platnosti akcí z API
     */
    private function isClaimed(OfferData $offer, array $claimed): bool
    {
        foreach ($claimed[$offer->externalId] ?? [] as [$from, $to]) {
            if ($from->lessThanOrEqualTo($offer->validTo) && $to->greaterThanOrEqualTo($offer->validFrom)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Letáky a katalogy, které ještě nezačaly a mají se stáhnout z PDF: hlavní leták, tematický
     * leták bez hlavního letáku v seznamu a katalog s potravinami, drogerií nebo krmivy. Seznam
     * letáků se stahuje od zástupce každého pásma — PDF stejného letáku má každý hypermarket vlastní;
     * leták nese PDF každého pásma, které ho má, s hypermarkety pásma.
     *
     * @param  non-empty-list<array{representative: string, houses: non-empty-list<string>}>  $zones  Výchozí první
     * @return list<array{id: string, name: string, type: string, validFrom: CarbonImmutable, validTo: CarbonImmutable, pdfs: list<array{url: string, representative: string, houses: list<string>}>}>
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     * @throws ConnectionException
     */
    private function upcomingLeaflets(array $zones): array
    {
        $pageSize = config()->integer('letaky.sources.globus.leaflets_page_size');
        $zoneHouses = [];
        foreach ($zones as $zone) {
            $zoneHouses[$zone['representative']] = $zone['houses'];
        }

        $leaflets = [];
        $pending = array_column($zones, 'representative');
        $page = 0;
        while ($pending !== []) {
            $responses = $this->pages($pending, 'leaflets_path', $page, $pageSize);
            $next = [];
            foreach ($pending as $representative) {
                $pageLeaflets = $this->parser->leafletsPage($this->json($responses[$representative]));
                foreach ($pageLeaflets as $leaflet) {
                    $leaflets[$leaflet['id']] ??= [
                        'id' => $leaflet['id'],
                        'name' => $leaflet['name'],
                        'type' => $leaflet['type'],
                        'validFrom' => $leaflet['validFrom'],
                        'validTo' => $leaflet['validTo'],
                        'pdfs' => [],
                    ];
                    $leaflets[$leaflet['id']]['pdfs'][] = ['url' => $leaflet['pdfUrl'], 'representative' => $representative, 'houses' => $zoneHouses[$representative]];
                }
                if (count($pageLeaflets) === $pageSize) {
                    $next[] = $representative;
                }
            }
            $pending = $next;
            $page++;
        }

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
     * Stejná stránka výpisu API několika hypermarketů souběžně.
     *
     * @param  list<string>  $houses
     * @param  string  $pathKey  Klíč cesty v konfiguraci zdroje
     * @param  int|null  $pageSize  Velikost stránky; null = `page_size` zdroje
     * @return array<string, Response> Odpověď podle hypermarketu
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    private function pages(array $houses, string $pathKey, int $page, ?int $pageSize = null): array
    {
        $query = ['page' => $page, 'pageSize' => $pageSize ?? config()->integer('letaky.sources.globus.page_size')];
        $requests = [];
        foreach ($houses as $house) {
            $requests[$house] = [
                sprintf(config()->string('letaky.sources.globus.api_url'), (int) $house).config()->string("letaky.sources.globus.{$pathKey}"),
                $query,
            ];
        }

        return $this->http->pool($requests, config()->integer('letaky.sources.globus.house_concurrency'));
    }

    /**
     * Tělo odpovědi API jako pole; jiný tvar je prázdné pole (parser ho odmítne).
     *
     * @return array<mixed>
     */
    private function json(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
