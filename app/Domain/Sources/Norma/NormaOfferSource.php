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

        return $batches === [] ? throw SourceResponseChanged::because(Chain::Norma, 'stránka nabídky bez odkazu na PDF letáku') : array_values($batches);
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
