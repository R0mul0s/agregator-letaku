<?php

/**
 * Zdroj Normy — akce s cenou z PDF letáků (R86, R166, ZDROJE_DAT.md, Norma). Česká část webu je na
 * `norma-online.de/cz/`; stránka s nabídkou odkazuje na PDF letáku tohoto a příštího týdne
 * (`online-prospekt/{rok}-{ISO týden}_CZ.pdf`). Leták platí od pondělí do neděle daného týdne,
 * víkendové strany kratší dobu (platnost strany čte parser).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Norma;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;

final class NormaOfferSource implements OfferSource
{
    /** Leták platí pondělí až neděle — konec je šest dní po začátku. */
    private const WEEK_LAST_DAY_OFFSET = 6;

    private const ISO_MONDAY = 1;

    /** Titulek stránky v chybové hlášce. */
    private const PAGE_TITLE_PATTERN = '#<title[^>]*>(.*?)</title>#is';

    /** Nejvíc znaků titulku v chybové hlášce (obsah stránky je cizí). */
    private const LOGGED_TITLE_LENGTH = 100;

    public function __construct(
        private readonly SourceHttp $http,
        private readonly NormaLeafletParser $parser,
        private readonly PdfTextReader $pdf,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Norma.
     */
    public function chain(): Chain
    {
        return Chain::Norma;
    }

    /**
     * Dávka za každý leták ze stránky s nabídkou. Nepovedené stažení nebo převod PDF ukončí
     * celé stažení chybou: bez akcí letáku by je import označil jako stažené obchodem (R16).
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    public function fetch(): array
    {
        $page = $this->http->request($this->delay())->get(config()->string('letaky.sources.norma.offers_page_url'))->body();
        preg_match_all(config()->string('letaky.sources.norma.pdf_link_pattern'), $page, $matches, PREG_SET_ORDER);

        $batches = [];
        // Každý leták má na stránce náhled i tlačítko — PDF jen jednou
        foreach ($matches as [, $path, $year, $week]) {
            $batches[$year.'-'.$week] ??= $this->batch(config()->string('letaky.sources.norma.base_url').$path, $year.'-'.$week, (int) $year, (int) $week);
        }

        return $batches === [] ? throw $this->pageWithoutLeaflets($page) : array_values($batches);
    }

    /**
     * Chyba stránky bez odkazu na leták s její velikostí a titulkem — 2. 11. 2026 v 6:20 taková
     * stránka přišla a o pár hodin později už odkazy měla, z logu nešlo poznat, co obsahovala.
     */
    private function pageWithoutLeaflets(string $page): SourceResponseChanged
    {
        $title = preg_match(self::PAGE_TITLE_PATTERN, $page, $match) === 1 ? Text::clean(html_entity_decode(strip_tags($match[1]))) : null;

        return SourceResponseChanged::because(Chain::Norma, sprintf(
            'stránka nabídky bez odkazu na PDF letáku (%d bajtů, titulek: %s)',
            strlen($page),
            Text::limit($title, self::LOGGED_TITLE_LENGTH) ?? 'žádný',
        ));
    }

    /**
     * Dávka jednoho letáku s platností týdne z názvu souboru.
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function batch(string $pdfUrl, string $number, int $year, int $week): SourceBatch
    {
        $monday = $this->calendar->date(CarbonImmutable::now()->setISODate($year, $week, self::ISO_MONDAY)->toDateString());
        $validity = [$monday, $monday->addDays(self::WEEK_LAST_DAY_OFFSET)];

        $offers = $this->parser->offers(
            $this->pdf->readUrl($this->http, $pdfUrl, $this->delay()),
            $validity,
            config()->string('letaky.sources.norma.offers_page_url'),
        );
        // Leták má ~110 ověřených akcí (měření R166); méně = změněné rozvržení nebo rozbitý parser
        if (count($offers) < config()->integer('letaky.sources.norma.pdf_min_offers')) {
            throw SourceResponseChanged::because(Chain::Norma, "leták {$number}: jen ".count($offers).' ověřených akcí');
        }

        return new SourceBatch(
            new LeafletData(LeafletKind::Leaflet, $number, validFrom: $validity[0], validTo: $validity[1], sourceUrl: $pdfUrl),
            $offers,
            tiles: $this->parser->lastStats(),
        );
    }

    /**
     * Pauza mezi požadavky.
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.norma.request_delay_ms');
    }
}
