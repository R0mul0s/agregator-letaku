<?php

/**
 * Čtení textu z vektorové vrstvy stránky letáku FlippingBook (`page-vectorlayers/NNNN.svg`).
 *
 * Každý `<svg:text transform="matrix(a b c d e f) scale(1, -1)">` obsahuje `<svg:tspan>`
 * s polohou každého znaku (`x` seznam, `y`) a barvou. Znaky se skládají do slov podle
 * mezer mezi nimi; poloha slova je poloha jeho prvního znaku na stránce.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Penny;

use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class SvgTextReader
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    /** Mezera mezi znaky (v jednotkách písma), od které začíná nové slovo. */
    private const WORD_GAP = 1.5;

    /** Transformace textu: matrix(a b c d e f). */
    private const MATRIX_PATTERN = '/matrix\(\s*([-\d.e]+)[\s,]+([-\d.e]+)[\s,]+([-\d.e]+)[\s,]+([-\d.e]+)[\s,]+([-\d.e]+)[\s,]+([-\d.e]+)\s*\)/i';

    /**
     * Slova stránky s polohou, velikostí a barvou.
     *
     * @return list<SvgToken>
     *
     * @throws SourceResponseChanged
     */
    public function tokens(string $svg): array
    {
        $document = new DOMDocument;
        // BOM na začátku souboru by XML parser odmítl
        if (! @$document->loadXML(preg_replace('/^\xEF\xBB\xBF/', '', $svg) ?? '', LIBXML_NONET | LIBXML_COMPACT)) {
            throw SourceResponseChanged::because(Chain::Penny, 'stránka letáku není platné SVG');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('svg', self::SVG_NAMESPACE);

        $tokens = [];
        foreach ($xpath->query('//svg:text') ?: [] as $text) {
            if (! $text instanceof DOMElement || preg_match(self::MATRIX_PATTERN, $text->getAttribute('transform'), $m) !== 1) {
                continue;
            }

            [$a, $b, $c, $d, $e, $f] = array_map(floatval(...), array_slice($m, 1));
            foreach ($xpath->query('svg:tspan', $text) ?: [] as $span) {
                if ($span instanceof DOMElement) {
                    array_push($tokens, ...$this->words($span, $a, $b, $c, $d, $e, $f));
                }
            }
        }

        return $tokens;
    }

    /**
     * Slova jednoho `<svg:tspan>`. Text je převrácený `scale(1, -1)`, proto y se znaménkem minus.
     *
     * @return list<SvgToken>
     */
    private function words(DOMElement $span, float $a, float $b, float $c, float $d, float $e, float $f): array
    {
        $characters = mb_str_split($span->textContent);
        $xs = array_map(floatval(...), preg_split('/\s+/', trim($span->getAttribute('x'))) ?: []);
        $ys = array_map(floatval(...), preg_split('/\s+/', trim($span->getAttribute('y'))) ?: []);
        $fill = $span->getAttribute('fill');
        $size = abs($d);

        $words = [];
        $current = null;
        $lastX = 0.0;
        foreach ($characters as $i => $character) {
            $x = $xs[$i] ?? end($xs) ?: 0.0;
            $y = $ys[$i] ?? $ys[0] ?? 0.0;

            if ($current === null || $x - $lastX > self::WORD_GAP) {
                if ($current !== null) {
                    $words[] = $current;
                }
                $current = ['x' => $a * $x - $c * $y + $e, 'y' => $b * $x - $d * $y + $f, 'text' => ''];
            }

            $current['text'] .= $character;
            $lastX = $x;
        }

        if ($current !== null) {
            $words[] = $current;
        }

        return array_map(fn (array $word): SvgToken => new SvgToken($word['x'], $word['y'], $size, $fill, $word['text']), $words);
    }
}
