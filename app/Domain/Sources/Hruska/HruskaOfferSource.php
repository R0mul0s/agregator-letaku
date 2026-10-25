<?php

/**
 * Zdroj Hrušky — akce s cenou z PDF letáku (R86, R165, ZDROJE_DAT.md, Hruška). Web nemá akce
 * ani API; úvodní stránka odkazuje na PDF aktuálního letáku. Leták dalšího týdne je na serveru
 * od pátku, ale nic na něj neodkazuje — adresa se odvodí z čísla týdne (`{rok}/{měsíc nahrání}/
 * {týden}_{rok2}.pdf`) a zkusí se pro několik měsíců nahrání.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Hruska;

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
use Symfony\Component\HttpFoundation\Response;

final class HruskaOfferSource implements OfferSource
{
    /** Leták dalšího týdne začíná o týden později než aktuální. */
    private const WEEK_DAYS = 7;

    /** Číslo letáku je dvoumístný rok („43_26“). */
    private const YEAR_SUFFIX_MODULO = 100;

    public function __construct(
        private readonly SourceHttp $http,
        private readonly HruskaLeafletParser $parser,
        private readonly PdfTextReader $pdf,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Hruška.
     */
    public function chain(): Chain
    {
        return Chain::Hruska;
    }

    /**
     * Dávka za aktuální leták a za leták dalšího týdne, když už je na serveru. Nepovedené stažení
     * nebo převod PDF ukončí celé stažení chybou: bez akcí letáku by je import označil jako
     * stažené obchodem (R16).
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    public function fetch(): array
    {
        $home = $this->http->request($this->delay())->get(config()->string('letaky.sources.hruska.base_url'))->body();
        if (preg_match(config()->string('letaky.sources.hruska.pdf_url_pattern'), $home, $m) !== 1) {
            throw SourceResponseChanged::because(Chain::Hruska, 'úvodní stránka bez odkazu na PDF letáku');
        }

        $current = $this->batch($m[0], $m[3].'_'.$m[4]);
        $batches = [$current];

        $nextStart = $current->leaflet->validFrom?->addDays(self::WEEK_DAYS);
        $nextUrl = $nextStart === null ? null : $this->nextLeafletUrl($nextStart);
        if ($nextUrl !== null) {
            $batches[] = $this->batch($nextUrl, $this->leafletNumber($nextStart));
        }

        return $batches;
    }

    /**
     * Dávka jednoho letáku: platnost z hlavičky první strany a ověřené akce.
     *
     * @throws SourceResponseChanged
     * @throws PdfTextFailed
     * @throws RequestException
     */
    private function batch(string $pdfUrl, string $number): SourceBatch
    {
        $pages = $this->pdf->readUrl($this->http, $pdfUrl, $this->delay());
        $validity = $this->parser->validity($pages)
            ?? throw SourceResponseChanged::because(Chain::Hruska, "leták {$number} bez platnosti na první straně");

        $offers = $this->parser->offers($pages, $validity, config()->string('letaky.sources.hruska.offers_page_url'));
        // Leták má ~170 ověřených akcí (měření R165); méně = změněné rozvržení nebo rozbitý parser
        if (count($offers) < config()->integer('letaky.sources.hruska.pdf_min_offers')) {
            throw SourceResponseChanged::because(Chain::Hruska, "leták {$number}: jen ".count($offers).' ověřených akcí');
        }

        return new SourceBatch(
            new LeafletData(LeafletKind::Leaflet, $number, validFrom: $validity[0], validTo: $validity[1], sourceUrl: $pdfUrl),
            $offers,
            tiles: $this->parser->lastStats(),
        );
    }

    /**
     * Adresa PDF letáku dalšího týdne, nebo null, když na serveru ještě není. Složka je měsíc
     * nahrání (týden až dva před začátkem) — zkusí se měsíc dneška, začátku a týden před ním.
     *
     * @throws RequestException
     */
    private function nextLeafletUrl(CarbonImmutable $start): ?string
    {
        [$week, $year] = explode('_', $this->leafletNumber($start));
        $months = [];
        foreach ([$this->calendar->today(), $start->subDays(self::WEEK_DAYS), $start] as $day) {
            $months[$day->format('Y-n')] = $day;
        }

        foreach ($months as $day) {
            $url = sprintf(config()->string('letaky.sources.hruska.pdf_url_format'), $day->year, $day->month, (int) $week, (int) $year);
            if ($this->http->request($this->delay(), allowNotFound: true)->head($url)->status() !== Response::HTTP_NOT_FOUND) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Číslo letáku podle začátku platnosti: ISO týden a dvoumístný rok („44_26“).
     */
    private function leafletNumber(CarbonImmutable $start): string
    {
        return (int) $start->format('W').'_'.sprintf('%02d', (int) $start->format('o') % self::YEAR_SUFFIX_MODULO);
    }

    /**
     * Pauza mezi požadavky.
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.hruska.request_delay_ms');
    }
}
