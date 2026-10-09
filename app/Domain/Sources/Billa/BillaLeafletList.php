<?php

/**
 * Seznam letáků Billy ze stránky `/akcni-letaky` a odkaz na PDF ze stránky letáku (R89, ZDROJE_DAT.md, Billa).
 *
 * Stránka letáků (Nuxt, server ji vykreslí celou) má karty `<a class="ws-teaser …" href="…"
 * data-teaser-name="Velký leták">` s platností v textu „Platí od středy 7. 10. do úterý 13. 10. 2026“
 * (i „od středa“). Odkaz karty vede na stránku letáku (`/letaky-billa?tab=letaky-billa/velky-letak-nasledujici`,
 * `/akcni-letaky/katalog-italie`), která má tlačítko s přímým odkazem na PDF na `view.publitas.com`.
 * Parsování nic nestahuje — HTML dostane od zdroje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use Carbon\CarbonImmutable;

final class BillaLeafletList
{
    /** Karta letáku: atributy odkazu a jeho obsah. */
    private const TEASER_PATTERN = '#<a\s([^>]*\bws-teaser\b[^>]*)>(.*?)</a>#s';

    private const HREF_PATTERN = '/\bhref="([^"]+)"/';

    private const TITLE_PATTERN = '/\bdata-teaser-name="([^"]*)"/';

    /** Platnost karty: „Platí od středy 30. 9. do úterý 6. 10. 2026“ (rok jen u konce). */
    private const VALIDITY_PATTERN = '/Platí\s+od\s+\p{L}+\s+(\d{1,2})\.\s*(\d{1,2})\.\s+do\s+\p{L}+\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u';

    /** Nezlomitelná mezera v textu karty („30.&nbsp;9.“). */
    private const NBSP = "\u{00A0}";

    public function __construct(
        private readonly LeafletDates $dates,
    ) {}

    /**
     * Karty letáků s platností; karty bez platnosti (magazín, „výhodně každý den“) se vynechají.
     * Stejná karta (odkaz a platnost) se vrátí jednou.
     *
     * @return list<array{path: string, title: string, validFrom: CarbonImmutable, validTo: CarbonImmutable}>
     *
     * @throws SourceResponseChanged
     */
    public function leaflets(string $html): array
    {
        preg_match_all(self::TEASER_PATTERN, $html, $teasers, PREG_SET_ORDER);

        $leaflets = [];
        foreach ($teasers as [, $attributes, $content]) {
            $text = $this->text($content);
            if (preg_match(self::HREF_PATTERN, $attributes, $href) !== 1 || preg_match(self::VALIDITY_PATTERN, $text, $m) !== 1) {
                continue;
            }

            $validity = $this->dates->range((int) $m[1], (int) $m[2], null, (int) $m[3], (int) $m[4], (int) $m[5]);
            if ($validity === null) {
                continue;
            }
            [$from, $to] = $validity;

            $path = html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5);
            $title = preg_match(self::TITLE_PATTERN, $attributes, $t) === 1 ? $this->text($t[1]) : $path;
            $leaflets[$path.'|'.$from->toDateString()] ??= ['path' => $path, 'title' => $title, 'validFrom' => $from, 'validTo' => $to];
        }

        if ($leaflets === []) {
            throw SourceResponseChanged::because(Chain::Billa, 'stránka letáků nemá žádnou kartu s platností');
        }

        return array_values($leaflets);
    }

    /**
     * Přímý odkaz na PDF ze stránky letáku — první odkaz podle `pdf_url_pattern` (tlačítko „Stáhnout“
     * aktivní záložky; data Nuxtu za ním mají i PDF ostatních záložek se znaky `/`, ty vzor nechytí).
     *
     * @throws SourceResponseChanged
     */
    public function pdfUrl(string $html): string
    {
        if (preg_match(config()->string('letaky.sources.billa.pdf_url_pattern'), $html, $m) !== 1) {
            throw SourceResponseChanged::because(Chain::Billa, 'stránka letáku nemá odkaz na PDF');
        }

        return $m[0];
    }

    /**
     * Text bez značek HTML, entit a nezlomitelných mezer.
     */
    private function text(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', str_replace(self::NBSP, ' ', $text)));
    }
}
