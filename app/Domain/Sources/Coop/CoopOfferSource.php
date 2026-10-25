<?php

/**
 * Zdroj COOP — akce s cenou z PDF letáků na coopclub.cz (R86, R167, ZDROJE_DAT.md, Coop). Textovou
 * vrstvu mají jen celostátní leták a regionální letáky Čech; moravská družstva vydávají letáky jako
 * obrázky. Seznam letáků je RSS kanál („SČ 22 (od 21.10. do 3.11.)“), stránka letáku odkazuje na PDF.
 *
 * Akce regionálního letáku platí jen v prodejnách regionu — regiony jsou „prodejny“ obchodu (migrace
 * 2026_10_25_100000); akce celostátního letáku platí všude. Stejná akce ve více regionálních letácích
 * platí ve všech těch regionech.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Coop;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\Data\TileStats;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use SimpleXMLElement;

final class CoopOfferSource implements OfferSource
{
    /** Titulek letáku v RSS: zkratka, číslo cyklu a platnost bez roku („SČ 22 (od 21.10. do 3.11.)“). */
    private const TITLE_PATTERN = '/^(\S+)\s+(\d+)\s*\(od\s+(\d{1,2})\.\s*(\d{1,2})\.\s+do\s+(\d{1,2})\.\s*(\d{1,2})\.\)/u';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly CoopLeafletParser $parser,
        private readonly PdfTextReader $pdf,
        private readonly LocalCalendar $calendar,
        private readonly LeafletDates $dates,
    ) {}

    /**
     * COOP.
     */
    public function chain(): Chain
    {
        return Chain::Coop;
    }

    /**
     * Dávka za každý celostátní a regionální leták, který ještě neskončil. Nepovedené stažení nebo
     * převod PDF ukončí celé stažení chybou: bez akcí letáku by je import označil jako stažené (R16).
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    public function fetch(): array
    {
        $leaflets = [];
        foreach ($this->feedItems() as [$code, $cycle, $link, $validity]) {
            $leaflets[] = $this->leaflet($code, $cycle, $link, $validity);
        }

        return $leaflets === [] ? throw SourceResponseChanged::because(Chain::Coop, 'RSS bez platného celostátního ani regionálního letáku') : $this->batches($leaflets);
    }

    /**
     * Letáky z RSS kanálu, které sledujeme (zkratka v `leaflets`) a ještě neskončily.
     *
     * @return list<array{string, string, string, array{CarbonImmutable, CarbonImmutable}}> Zkratka, cyklus, stránka letáku, platnost
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    private function feedItems(): array
    {
        $body = $this->http->request($this->delay())->get(config()->string('letaky.sources.coop.feed_url'))->body();
        $feed = @simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET);
        if ($feed === false || ! isset($feed->channel->item)) {
            throw SourceResponseChanged::because(Chain::Coop, 'RSS letáků není čitelné');
        }

        $regions = config()->array('letaky.sources.coop.leaflets');
        $today = $this->calendar->today();
        $items = [];
        foreach ($feed->channel->item as $item) {
            if (preg_match(self::TITLE_PATTERN, trim((string) $item->title), $m) !== 1 || ! array_key_exists($m[1], $regions)) {
                continue;
            }

            // Rok v titulku chybí — podle data zveřejnění (i přes Nový rok, LeafletDates)
            $published = $this->calendar->dateOfInstant(CarbonImmutable::parse((string) $item->pubDate));
            $validity = $this->dates->range((int) $m[3], (int) $m[4], null, (int) $m[5], (int) $m[6], null, $published);
            if ($validity !== null && ! $validity[1]->lt($today)) {
                $items[] = [$m[1], $m[2], trim((string) $item->link), $validity];
            }
        }

        // Celostátní leták první — akci, kterou mají i regionální letáky, uloží jeho dávka
        usort($items, fn (array $a, array $b): int => ($regions[$a[0]] !== null) <=> ($regions[$b[0]] !== null));

        return $items;
    }

    /**
     * Leták: odkaz na PDF ze stránky letáku, platnost z hlavičky PDF (jinak z RSS) a ověřené akce.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $feedValidity
     * @return array{LeafletData, list<OfferData>, TileStats, string|null}
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function leaflet(string $code, string $cycle, string $link, array $feedValidity): array
    {
        $page = $this->http->request($this->delay())->get($link)->body();
        if (preg_match(config()->string('letaky.sources.coop.pdf_url_pattern'), $page, $m) !== 1) {
            throw SourceResponseChanged::because(Chain::Coop, "stránka letáku {$code} {$cycle} bez odkazu na PDF");
        }

        $pages = $this->pdf->readUrl($this->http, $m[0], $this->delay());
        $validity = $this->parser->validity($pages) ?? $feedValidity;
        $number = "{$code} {$cycle}/{$validity[0]->year}";
        $offers = $this->parser->offers($pages, $validity, $link);
        // Leták má 45–75 ověřených akcí (měření R167); méně = změněné rozvržení nebo rozbitý parser
        if (count($offers) < config()->integer('letaky.sources.coop.pdf_min_offers')) {
            throw SourceResponseChanged::because(Chain::Coop, "leták {$number}: jen ".count($offers).' ověřených akcí');
        }

        $region = config()->array('letaky.sources.coop.leaflets')[$code] ?? null;

        return [
            new LeafletData(LeafletKind::Leaflet, $number, title: $code, validFrom: $validity[0], validTo: $validity[1], sourceUrl: $link),
            $offers,
            $this->parser->lastStats(),
            is_string($region) ? $region : null,
        ];
    }

    /**
     * Dávky letáků s prodejnami (regiony) akcí. Akce celostátního letáku platí všude; akce jen
     * z regionálních letáků platí v jejich regionech. Stejnou akci uloží první dávka, import ji
     * v další přeskočí (jako Albert HM a SM).
     *
     * @param  list<array{LeafletData, list<OfferData>, TileStats, string|null}>  $leaflets
     * @return list<SourceBatch>
     */
    private function batches(array $leaflets): array
    {
        $regions = [];
        foreach ($leaflets as [, $offers, , $region]) {
            foreach ($offers as $offer) {
                $regions[$offer->key()][] = $region;
            }
        }

        return array_map(fn (array $leaflet): SourceBatch => new SourceBatch(
            $leaflet[0],
            array_map(function (OfferData $offer) use ($regions): OfferData {
                $codes = $regions[$offer->key()];

                return $offer->withStoreCodes(in_array(null, $codes, true) ? null : array_values(array_unique(array_filter($codes))));
            }, $leaflet[1]),
            tiles: $leaflet[2],
        ), $leaflets);
    }

    /**
     * Pauza mezi požadavky.
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.coop.request_delay_ms');
    }
}
