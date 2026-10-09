<?php

/**
 * Text s polohou z PDF letáku přes `pdftotext -bbox-layout` (Poppler, R86).
 *
 * Hosting (Websupport) má pdftotext 22.02 a dovoluje spouštět programy (proc_open); PDF
 * zpracuje program, ne PHP, takže ani 30MB leták nezatíží paměť. Výstup je XHTML
 * `page > flow > block > line > word` se souřadnicemi `xMin/yMin/xMax/yMax` v bodech PDF.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Pdf;

use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\SourceHttp;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Process;

final class PdfTextReader
{
    /** Předpona dočasného souboru s PDF. */
    private const TEMP_PREFIX = 'letak-';

    /** Řídicí znaky, které XML 1.0 nedovoluje (tabulátor a konce řádků ano). */
    private const CONTROL_CHARACTERS = '/[\x00-\x08\x0B\x0C\x0E-\x1F]/';

    /**
     * Stáhne PDF letáku do dočasného souboru a vrátí jeho stránky s textem a polohou slov.
     * Soubor se nedrží v paměti a po přečtení se smaže. HTTP klient je zdroje — drží jeho
     * pauzu mezi požadavky.
     *
     * @param  int|null  $delayMs  Pauza zdroje; null = výchozí z konfigurace
     * @return list<PdfPage>
     *
     * @throws PdfTextFailed
     * @throws RequestException PDF se nestáhlo
     */
    public function readUrl(SourceHttp $http, string $url, ?int $delayMs = null): array
    {
        $path = tempnam(sys_get_temp_dir(), self::TEMP_PREFIX);
        if ($path === false) {
            throw PdfTextFailed::because('nejde vytvořit dočasný soubor');
        }

        try {
            $http->download($url, $path, $delayMs);

            return $this->readFile($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Stránky PDF souboru na disku.
     *
     * @return list<PdfPage>
     *
     * @throws PdfTextFailed
     */
    private function readFile(string $path): array
    {
        $result = Process::timeout(config()->integer('letaky.pdf.timeout_seconds'))
            ->run([config()->string('letaky.pdf.pdftotext_binary'), '-bbox-layout', '-q', $path, '-']);

        if (! $result->successful()) {
            throw PdfTextFailed::because("pdftotext skončil s kódem {$result->exitCode()}: ".trim($result->errorOutput()));
        }

        return $this->parse($result->output());
    }

    /**
     * Převede výstup `pdftotext -bbox-layout` na stránky.
     *
     * @return list<PdfPage>
     *
     * @throws PdfTextFailed
     */
    public function parse(string $xhtml): array
    {
        // Glyfy vlastních fontů dávají řídicí znaky (Albert U+0007), které XML nedovoluje
        $xhtml = (string) preg_replace(self::CONTROL_CHARACTERS, '', $xhtml);

        $document = new DOMDocument;
        // Bez načítání DTD ze sítě; nečitelné XML = chyba, ne prázdný leták
        if ($xhtml === '' || ! @$document->loadXML($xhtml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw PdfTextFailed::because('výstup není XML');
        }

        $pages = [];
        foreach ($document->getElementsByTagName('page') as $page) {
            $lines = [];
            foreach ($page->getElementsByTagName('block') as $blockIndex => $block) {
                foreach ($block->getElementsByTagName('line') as $line) {
                    $words = [];
                    foreach ($line->getElementsByTagName('word') as $word) {
                        $text = trim($word->textContent);
                        if ($text !== '') {
                            $words[] = new PdfWord($text, ...$this->box($word));
                        }
                    }
                    if ($words !== []) {
                        $lines[] = new PdfLine($words, $blockIndex, ...$this->box($line));
                    }
                }
            }

            $pages[] = new PdfPage(count($pages) + 1, (float) $page->getAttribute('width'), (float) $page->getAttribute('height'), $lines);
        }

        return $pages === [] ? throw PdfTextFailed::because('žádná stránka') : $pages;
    }

    /**
     * Souřadnice prvku: xMin, yMin, xMax, yMax.
     *
     * @return array{float, float, float, float}
     */
    private function box(DOMElement $element): array
    {
        return [
            (float) $element->getAttribute('xMin'),
            (float) $element->getAttribute('yMin'),
            (float) $element->getAttribute('xMax'),
            (float) $element->getAttribute('yMax'),
        ];
    }
}
