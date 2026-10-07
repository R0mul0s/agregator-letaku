<?php

/**
 * Nabídky z vektorové vrstvy letáku Penny — bez LLM (R23, R26, R85, ZDROJE_DAT.md, Penny).
 *
 * Postup na stránce:
 * 1. **Ceny:** velké číslo s glyfem „Ǻ“ (= „,90“), červené nebo bílé.
 * 2. **Bloky textu:** řádky zarovnané pod sebou (stejné x, mezery do ~10 bodů) — název,
 *    upřesnění, balení a cena za jednotku jedné dlaždice. Řádek končící „|“ pokračuje tokenem
 *    vpravo na stejném účaří („500 g |“ + „100 g 3,98 Kč“), text má nezlomitelné mezery (U+00A0).
 * 3. **První kolo — ověření cenou za jednotku:** cena přepočtená na balení musí dát cenu za
 *    jednotku uvedenou v bloku („250 g“ + „100 g 5,16 Kč“ ↔ 12,90 Kč; i „1 kg 49 Kč“
 *    a varianty „270/280 g“ + „100 g 7,37/7,11 Kč“). U balení 1 kg / 1 l / 1 ks („cena za 1 kg“)
 *    cena za jednotku chybí, pak musí blok ležet přímo nad cenou. Každý blok patří nejvýš
 *    jedné ceně — nejbližší dvojice, pak rozšiřující cesty (dvě sousední stejné ceny).
 * 4. **Druhé kolo — rozvržení (R85):** blok bez ceny za jednotku patří k ceně, když vůči ní
 *    leží se stejným posunem jako bloky ověřených dlaždic stránky nebo celého letáku a cena
 *    i blok mají jediného kandidáta. Co se ověřit nedá, se neuloží.
 * 5. **Přeškrtnutá cena:** malé číslo vpravo pod cenou, za ním „,“ + glyfy haléřů
 *    (U+E00A U+E009 = „90“) a červená přeškrtávací čára (U+E00F / E010 / E011 podle délky);
 *    bez čáry jen tehdy, když sedí procento slevy ze štítku („33 %“).
 * 6. **PENNY karta:** velká cena je cena s kartou (`loyalty_price`), malé číslo u „cena bez
 *    pennykarty“ je běžná cena. **Cena za více kusů** („při koupi 1 ks cena 169,90 Kč od 2 ks
 *    cena 149,90 Kč“): běžná cena kusu a výhodná v textu akce, jako u Billy (R48).
 * 7. Platnost: „platí od … do …“ na stránce, jinak „Nabídka platná od … do …“, jinak
 *    platnost letáku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Penny;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Pdf\DiscountCheck;
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type Anchor array{token: SvgToken, price: int, regular: ?int}
 * @phpstan-type Tile array{name: string, package: string, text: string}
 * @phpstan-type Candidate array{anchor: int, block: int, tile: Tile, distance: float}
 * @phpstan-type Offset array{float, float}
 * @phpstan-type Block non-empty-list<SvgToken>
 */
final class PennyLeafletParser
{
    private const RED = 'rgb(255,45,22)';

    private const WHITE = 'rgb(255,255,255)';

    /** Velká cena „24Ǻ“ = 24,90 Kč (glyf Ǻ U+01FA je „,90“). */
    private const BIG_PRICE_PATTERN = '/^(\d{1,4})\x{01FA}$/u';

    /** Haléře ve velké ceně i v malých cenách — v letáku jsou všechny ceny ,90. */
    private const PRICE_FRACTION = '90';

    /** Velikost písma velké ceny. */
    private const BIG_PRICE_MIN_SIZE = 15.0;

    /** Velikost písma textu dlaždice (název, balení, cena za jednotku). */
    private const TEXT_MIN_SIZE = 4.0;

    private const TEXT_MAX_SIZE = 10.0;

    /** Odchylka x řádků jednoho bloku a největší mezera mezi řádky. */
    private const COLUMN_TOLERANCE = 3.0;

    private const LINE_GAP = 10.0;

    /** Řádek končící „|“ (i s nezlomitelnou mezerou) pokračuje dalším tokenem na stejném účaří. */
    private const CONTINUATION_PATTERN = '/\|[\s\x{00A0}]*$/u';

    /** Pokračování řádku: nejvýš tak daleko vpravo a tak málo mimo účaří (body stránky). */
    private const CONTINUATION_MAX_DISTANCE = 60.0;

    private const CONTINUATION_BASELINE_TOLERANCE = 0.8;

    /** Kde blok vůči ceně hledat (body stránky); vlevo i bloky dlaždic s cenou vpravo (−92 b.). */
    private const BLOCK_LEFT = 100.0;

    private const BLOCK_RIGHT = 70.0;

    private const BLOCK_ABOVE = 90.0;

    private const BLOCK_BELOW = 30.0;

    /** Blok bez ceny za jednotku musí ležet přímo nad cenou. */
    private const SINGLE_UNIT_X_TOLERANCE = 15.0;

    private const SINGLE_UNIT_MAX_ABOVE = 110.0;

    /** Rozvržení (R85): odchylka posunu bloku od posunu ověřených dlaždic (body stránky). */
    private const LAYOUT_TOLERANCE = 3.0;

    /** Kolik ověřených dlaždic se stejným posunem stačí na stránce a kolik v celém letáku. */
    private const LAYOUT_PAGE_MIN_TILES = 3;

    private const LAYOUT_LEAFLET_MIN_TILES = 8;

    /** Přeškrtnutá cena vůči velké ceně. */
    private const CROSSED_RIGHT_MIN = 5.0;

    private const CROSSED_RIGHT_MAX = 40.0;

    private const CROSSED_BELOW = 12.0;

    private const STRIKE_DISTANCE = 25.0;

    private const STRIKE_BASELINE_TOLERANCE = 2.0;

    private const STRIKE_PATTERN = '/^[\x{E00F}\x{E010}\x{E011}]$/u';

    /** Malá cena v letáku: jen koruny, haléře jsou glyfy. */
    private const SMALL_PRICE_PATTERN = '/^\d{1,4}$/';

    /** Štítek slevy „33 %“ nad cenou: kde leží „%“ vůči ceně a číslo vůči „%“ (body stránky). */
    private const BADGE_RIGHT_MIN = 5.0;

    private const BADGE_RIGHT_MAX = 45.0;

    private const BADGE_ABOVE_MIN = 15.0;

    private const BADGE_ABOVE_MAX = 40.0;

    private const BADGE_NUMBER_MAX_GAP = 22.0;

    private const BADGE_BASELINE_TOLERANCE = 2.5;

    private const BADGE_NUMBER_PATTERN = '/^\d{1,2}$/';

    /** Odchylka procenta slevy spočteného z cen od štítku (procentní body, zaokrouhlení obchodu). */
    private const BADGE_TOLERANCE = 1;

    /** Okolí ceny, ve kterém nápis „Karta“ znamená dlaždici s PENNY kartou. */
    private const CARD_DISTANCE_X = 90.0;

    private const CARD_DISTANCE_Y = 60.0;

    private const CARD_PATTERN = '/karta|pennykart/iu';

    /** Štítek karty je drobný; nadpis oddílu „MOJE PENNY KARTA“ (10,2 b.) dlaždici s kartou neznamená. */
    private const CARD_LABEL_MAX_SIZE = 8.0;

    /** Popisek běžné ceny u dlaždice s kartou a jeho okolí vůči ceně s kartou. */
    private const CARD_REGULAR_LABEL_PATTERN = '/cena[\s\x{00A0}]+bez[\s\x{00A0}]+pennykarty/iu';

    private const CARD_REGULAR_LABEL_DISTANCE_X = 40.0;

    private const CARD_REGULAR_LABEL_DISTANCE_Y = 12.0;

    /** Běžná cena u dlaždice s kartou leží níž než přeškrtnutá cena. */
    private const CARD_REGULAR_BELOW = 24.0;

    /** Značka ceny za více kusů u ceny: „při koupi 2 a více ks“, velké „od ks“. */
    private const MULTIBUY_MARKER_PATTERN = '/při[\s\x{00A0}]+koupi[\s\x{00A0}]+\d+[\s\x{00A0}]+a[\s\x{00A0}]+více|^od[\s\x{00A0}]+ks$/iu';

    private const MULTIBUY_MARKER_DISTANCE_X = 40.0;

    private const MULTIBUY_MARKER_DISTANCE_Y = 20.0;

    /** Ceny za více kusů v bloku: „při koupi 1 ks cena 169,90 Kč od 2 ks cena 149,90 Kč“. */
    private const MULTIBUY_PATTERN = '/při\s+koupi\s+1\s*ks\s+cena\s+(\d+(?:[.,]\d{2})?)\s*Kč\s+(od\s+\d+\s*ks)\s+cena\s+(\d+(?:[.,]\d{2})?)\s*Kč/iu';

    /** Oddělovač podmínky a ceny v textu akce na více kusů — jako u Billy. */
    private const MULTIBUY_PRICE_SEPARATOR = ': ';

    /** Cena za jednotku v bloku: „100 g 5,16 Kč“, „1l 14,60Kč“, „1 kg = 39,80 Kč“, „1 kg 49 Kč“. */
    private const UNIT_PRICE_PATTERN = '/(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l|ks)\s*(?:=\s*)?(\d+(?:[.,]\d{2})?)\s*Kč/iu';

    /** Cena za jednotku rozdělená na dva řádky: „100g“ + „31,96 Kč“. */
    private const SPLIT_UNIT_SIZE_PATTERN = '/\d+(?:[.,]\d+)?\s*(?:g|kg|ml|l|ks)\s*$/iu';

    private const SPLIT_UNIT_PRICE_PATTERN = '/^\d+(?:[.,]\d{2})?\s*Kč/u';

    /** Balení v řádku bloku: „250 g“, „1,5l“, „40ks“, „2 x 250 ml“. */
    private const PACKAGE_PATTERN = '/(?:(\d+)\s*[x×]\s*)?(\d+(?:[.,]\d+)?)\s*(kg|ks|ml|g|l)(?![\p{L}\d])/iu';

    /** Varianty balení: „270/280 g“, „50–57g“. */
    private const VARIANT_PACKAGE_PATTERN = '/(\d+(?:[.,]\d+)?)\s*[\/–-]\s*(\d+(?:[.,]\d+)?)\s*(kg|ks|ml|g|l)(?![\p{L}\d])/iu';

    /** Ceny za jednotku variant: „100g 7,37/7,11 Kč“, „1kg 378,03–361,59Kč“. */
    private const VARIANT_UNIT_PATTERN = '/(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l|ks)\s*(\d+[.,]\d{2})\s*[\/–-]\s*(\d+[.,]\d{2})\s*Kč/iu';

    /** Balení jedné jednotky bez ceny za jednotku: „1l“, „1 kg“, „cena za 1 kg“. */
    private const SINGLE_UNIT_PATTERN = '/(?:^|cena za\s*)1\s*(kg|l|ks)\s*$/iu';

    /** „cena za 1 kg“ kdekoli v bloku, i rozdělené na dva řádky („cena“ / „za 1 kg“). */
    private const PRICE_PER_SINGLE_UNIT_PATTERN = '/cena\s+za\s+1\s*(kg|ks|l)\b/iu';

    /** Název musí obsahovat slovo (aspoň tři písmena) — jinak blok nepatří k dlaždici. */
    private const NAME_PATTERN = '/\p{L}{3}/u';

    /** Řádek, který nepatří do názvu (cena za 30 dní „< 13,90 Kč“ apod.). */
    private const NOT_NAME_PATTERN = '/Kč|^<$/u';

    /** Platnost na stránce: „od pátku 2. 10. do neděle 4. 10. 2026“. */
    private const VALIDITY_PATTERN = '/(platí|platná)\s+od\s+\p{L}+\s+(\d{1,2})\.\s*(\d{1,2})\.\s*do\s+\p{L}+\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/u';

    /** Externí ID nabídky z letáku — leták nemá kód zboží, ID je otisk názvu, balení a ceny. */
    private const EXTERNAL_ID_PREFIX = 'letak-';

    private const EXTERNAL_ID_LENGTH = 16;

    public function __construct(
        private readonly PackageParser $packages,
        private readonly PriceParser $prices,
        private readonly PriceFormatter $formatter,
        private readonly VariantNote $variants,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Platnost stránky z textu: „platí od …“ (zvláštní oddíl) má přednost před „Nabídka platná od …“.
     *
     * @param  list<SvgToken>  $tokens
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function pageValidity(array $tokens): ?array
    {
        $found = [];
        foreach ($tokens as $token) {
            if (preg_match(self::VALIDITY_PATTERN, $token->text, $m) === 1) {
                $found[$m[1]] ??= [
                    $this->calendar->date(sprintf('%04d-%02d-%02d', $m[6], $m[3], $m[2])),
                    $this->calendar->date(sprintf('%04d-%02d-%02d', $m[6], $m[5], $m[4])),
                ];
            }
        }

        return $found['platí'] ?? $found['platná'] ?? null;
    }

    /**
     * Text stránky pro zmínky bez ceny (R27) — kusy textu shora dolů a zleva doprava;
     * null pro stránku bez textu.
     *
     * @param  list<SvgToken>  $tokens
     */
    public function pageText(array $tokens): ?string
    {
        usort($tokens, fn (SvgToken $a, SvgToken $b): int => [$b->y, $a->x] <=> [$a->y, $b->x]);

        return Text::join(...array_map(fn (SvgToken $token): string => $token->text, $tokens));
    }

    /**
     * Posuny bloků dlaždic stránky ověřených cenou za jednotku vůči jejich ceně — podklad
     * pro rozvržení celého letáku (commonOffsets).
     *
     * @param  list<SvgToken>  $tokens
     * @return list<Offset>
     */
    public function tileOffsets(array $tokens): array
    {
        $blocks = $this->blocks($tokens);
        [$anchors, $candidates, $matches] = $this->verifiedMatches($tokens, $blocks);

        return $this->offsets($anchors, $blocks, $candidates, $matches);
    }

    /**
     * Posuny, které v celém letáku sdílí dost ověřených dlaždic (R85).
     *
     * @param  list<Offset>  $offsets  Posuny ze všech stránek (tileOffsets)
     * @return list<Offset>
     */
    public function commonOffsets(array $offsets): array
    {
        return $this->supportedOffsets($offsets, self::LAYOUT_LEAFLET_MIN_TILES);
    }

    /**
     * Ověřené nabídky stránky — první kolo cenou za jednotku, druhé rozvržením dlaždic.
     *
     * @param  list<SvgToken>  $tokens
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     * @param  list<Offset>  $leafletOffsets  Posuny společné celému letáku (commonOffsets)
     * @return list<OfferData>
     */
    public function offers(array $tokens, array $validity, int $page, string $pageUrl, array $leafletOffsets = []): array
    {
        $blocks = $this->blocks($tokens);
        [$anchors, $candidates, $matches] = $this->verifiedMatches($tokens, $blocks);

        $tiles = [];
        foreach ($matches as $anchor => $candidate) {
            $tiles[$anchor] = [$candidates[$candidate]['block'], $candidates[$candidate]['tile']];
        }

        $layout = [
            ...$this->supportedOffsets($this->offsets($anchors, $blocks, $candidates, $matches), self::LAYOUT_PAGE_MIN_TILES),
            ...$leafletOffsets,
        ];
        $tiles += $this->layoutTiles($anchors, $blocks, $tiles, $layout);

        $offers = [];
        foreach ($tiles as $anchor => [, $tile]) {
            $offer = $this->offer($tile, $anchors[$anchor], $tokens, $validity, $page, $pageUrl);
            if ($offer !== null) {
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    /**
     * První kolo: ceny stránky, jejich kandidáti ověření cenou za jednotku a přiřazení.
     *
     * @param  list<SvgToken>  $tokens
     * @param  list<Block>  $blocks
     * @return array{list<Anchor>, list<Candidate>, array<int, int>}
     */
    private function verifiedMatches(array $tokens, array $blocks): array
    {
        $anchors = $this->anchors($tokens);
        $candidates = [];
        foreach ($anchors as $index => $anchor) {
            foreach ($blocks as $blockIndex => $block) {
                $tile = $this->matchBlock($block, $anchor['token'], $anchor['price']);
                if ($tile !== null && preg_match(self::NAME_PATTERN, $tile['name']) === 1) {
                    $candidates[] = [
                        'anchor' => $index,
                        'block' => $blockIndex,
                        'tile' => $tile,
                        'distance' => hypot($block[0]->x - $anchor['token']->x, end($block)->y - $anchor['token']->y),
                    ];
                }
            }
        }

        return [$anchors, $candidates, $this->assign($candidates)];
    }

    /**
     * Velké ceny stránky. Dlaždice s PENNY kartou jen s nalezenou cenou bez karty, jinak se přeskočí.
     *
     * @param  list<SvgToken>  $tokens
     * @return list<Anchor>
     */
    private function anchors(array $tokens): array
    {
        $anchors = [];
        foreach ($tokens as $token) {
            $price = $this->bigPrice($token);
            if ($price === null) {
                continue;
            }

            $regular = null;
            if ($this->isCardTile($tokens, $token)) {
                $regular = $this->cardRegularPrice($tokens, $token, $price);
                if ($regular === null) {
                    continue;
                }
            }

            $anchors[] = ['token' => $token, 'price' => $price, 'regular' => $regular];
        }

        return $anchors;
    }

    /**
     * Každá cena i každý blok nejvýš jednou: nejdřív nejbližší dvojice, pak rozšiřující cesty —
     * cena bez bloku vezme blok jiné ceny, když ta má jiný volný blok (sousední stejné ceny).
     *
     * @param  list<Candidate>  $candidates
     * @return array<int, int> Index ceny => index kandidáta
     */
    private function assign(array $candidates): array
    {
        $order = array_keys($candidates);
        usort($order, fn (int $a, int $b): int => $candidates[$a]['distance'] <=> $candidates[$b]['distance']);

        $matches = [];
        $owners = [];
        $edges = [];
        foreach ($order as $index) {
            $candidate = $candidates[$index];
            $edges[$candidate['anchor']][] = $index;
            if (! isset($matches[$candidate['anchor']]) && ! isset($owners[$candidate['block']])) {
                $matches[$candidate['anchor']] = $index;
                $owners[$candidate['block']] = $candidate['anchor'];
            }
        }

        foreach (array_keys($edges) as $anchor) {
            if (! isset($matches[$anchor])) {
                $visited = [];
                $this->augment($anchor, $candidates, $edges, $matches, $owners, $visited);
            }
        }

        return $matches;
    }

    /**
     * Rozšiřující cesta pro cenu bez bloku (Kuhnův algoritmus párování); true, když blok získala.
     *
     * @param  list<Candidate>  $candidates
     * @param  array<int, list<int>>  $edges  Index ceny => indexy jejích kandidátů od nejbližšího
     * @param  array<int, int>  $matches  Index ceny => index kandidáta
     * @param  array<int, int>  $owners  Index bloku => index ceny
     * @param  array<int, true>  $visited  Bloky, které už cesta prošla
     */
    private function augment(int $anchor, array $candidates, array $edges, array &$matches, array &$owners, array &$visited): bool
    {
        foreach ($edges[$anchor] ?? [] as $index) {
            $block = $candidates[$index]['block'];
            if (isset($visited[$block])) {
                continue;
            }
            $visited[$block] = true;

            if (! isset($owners[$block]) || $this->augment($owners[$block], $candidates, $edges, $matches, $owners, $visited)) {
                $matches[$anchor] = $index;
                $owners[$block] = $anchor;

                return true;
            }
        }

        return false;
    }

    /**
     * Posuny bloků přiřazených cen.
     *
     * @param  list<Anchor>  $anchors
     * @param  list<Block>  $blocks
     * @param  list<Candidate>  $candidates
     * @param  array<int, int>  $matches
     * @return list<Offset>
     */
    private function offsets(array $anchors, array $blocks, array $candidates, array $matches): array
    {
        $offsets = [];
        foreach ($matches as $anchor => $candidate) {
            $offsets[] = $this->offset($blocks[$candidates[$candidate]['block']], $anchors[$anchor]['token']);
        }

        return $offsets;
    }

    /**
     * Posun bloku vůči ceně: x prvního řádku a y spodního řádku.
     *
     * @param  Block  $block
     * @return Offset
     */
    private function offset(array $block, SvgToken $anchor): array
    {
        return [$block[0]->x - $anchor->x, end($block)->y - $anchor->y];
    }

    /**
     * Posuny, které sdílí aspoň daný počet dlaždic (každý posun počítá i sám sebe).
     *
     * @param  list<Offset>  $offsets
     * @return list<Offset>
     */
    private function supportedOffsets(array $offsets, int $minTiles): array
    {
        return array_values(array_filter($offsets, fn (array $offset): bool => count(array_filter(
            $offsets,
            fn (array $other): bool => $this->sameOffset($offset, $other),
        )) >= $minTiles));
    }

    /**
     * Leží dva posuny na stejném místě rozvržení?
     *
     * @param  Offset  $a
     * @param  Offset  $b
     */
    private function sameOffset(array $a, array $b): bool
    {
        return abs($a[0] - $b[0]) <= self::LAYOUT_TOLERANCE && abs($a[1] - $b[1]) <= self::LAYOUT_TOLERANCE;
    }

    /**
     * Druhé kolo (R85): blok bez ceny za jednotku patří k ceně, když vůči ní leží se stejným
     * posunem jako bloky ověřených dlaždic. Cena i blok musí mít jediného kandidáta.
     *
     * @param  list<Anchor>  $anchors
     * @param  list<Block>  $blocks
     * @param  array<int, array{int, Tile}>  $verified  Index ceny => [index bloku, dlaždice] z prvního kola
     * @param  list<Offset>  $layout
     * @return array<int, array{int, Tile}>
     */
    private function layoutTiles(array $anchors, array $blocks, array $verified, array $layout): array
    {
        if ($layout === []) {
            return [];
        }

        $usedBlocks = array_flip(array_map(fn (array $tile): int => $tile[0], $verified));
        $found = [];
        $perBlock = [];
        foreach ($anchors as $index => $anchor) {
            if (isset($verified[$index])) {
                continue;
            }

            foreach ($blocks as $blockIndex => $block) {
                if (isset($usedBlocks[$blockIndex]) || ! $this->fitsLayout($this->offset($block, $anchor['token']), $layout)) {
                    continue;
                }

                $tile = $this->layoutTile($block);
                if ($tile !== null && preg_match(self::NAME_PATTERN, $tile['name']) === 1) {
                    $found[$index][$blockIndex] = $tile;
                    $perBlock[$blockIndex] = ($perBlock[$blockIndex] ?? 0) + 1;
                }
            }
        }

        $tiles = [];
        foreach ($found as $index => $byBlock) {
            $blockIndex = array_key_first($byBlock);
            if (count($byBlock) === 1 && $perBlock[$blockIndex] === 1) {
                $tiles[$index] = [$blockIndex, $byBlock[$blockIndex]];
            }
        }

        return $tiles;
    }

    /**
     * Leží blok s daným posunem na místě některé ověřené dlaždice?
     *
     * @param  Offset  $offset
     * @param  list<Offset>  $layout
     */
    private function fitsLayout(array $offset, array $layout): bool
    {
        foreach ($layout as $known) {
            if ($this->sameOffset($offset, $known)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Název a balení bloku pro kolo rozvržení; null, když blok balení nemá, nebo má cenu za
     * jednotku (ta by ho ověřila v prvním kole — nesedí-li, blok k ceně nepatří).
     *
     * @param  Block  $block
     * @return Tile|null
     */
    private function layoutTile(array $block): ?array
    {
        $lines = $this->lines($block);
        $text = implode(' ', $lines);
        foreach ($lines as $line) {
            if (preg_match(self::UNIT_PRICE_PATTERN, $line) === 1 || preg_match(self::VARIANT_UNIT_PATTERN, $line) === 1) {
                return null;
            }
        }

        if (preg_match(self::PRICE_PER_SINGLE_UNIT_PATTERN, $text, $m) === 1) {
            return ['name' => $this->name([], mb_substr($text, 0, (int) mb_strpos($text, $m[0]))), 'package' => '1 '.mb_strtolower($m[1]), 'text' => $text];
        }

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (! str_contains($lines[$i], 'Kč')
                && (preg_match(self::VARIANT_PACKAGE_PATTERN, $lines[$i], $m) === 1 || preg_match(self::PACKAGE_PATTERN, $lines[$i], $m) === 1)) {
                return [
                    'name' => $this->name(array_slice($lines, 0, $i), mb_substr($lines[$i], 0, (int) mb_strpos($lines[$i], $m[0]))),
                    'package' => $m[0],
                    'text' => $text,
                ];
            }
        }

        return null;
    }

    /**
     * Cena v haléřích z velké ceny; null pro jiný text.
     */
    private function bigPrice(SvgToken $token): ?int
    {
        if ($token->size < self::BIG_PRICE_MIN_SIZE || ! in_array($token->fill, [self::RED, self::WHITE], true)
            || preg_match(self::BIG_PRICE_PATTERN, trim($token->text), $m) !== 1) {
            return null;
        }

        return $this->smallPrice($m[1]);
    }

    /**
     * Cena z korun v letáku — haléře jsou vždy ,90.
     */
    private function smallPrice(string $crowns): int
    {
        return $this->prices->parse($crowns.','.self::PRICE_FRACTION);
    }

    /**
     * Bloky textu: řádky se stejným x pod sebou, shora dolů.
     *
     * @param  list<SvgToken>  $tokens
     * @return list<Block>
     */
    private function blocks(array $tokens): array
    {
        $text = $this->joinContinuations(array_values(array_filter($tokens, fn (SvgToken $t): bool => $t->fill !== self::RED
            && $t->size >= self::TEXT_MIN_SIZE && $t->size <= self::TEXT_MAX_SIZE && trim($t->text) !== '')));

        $columns = [];
        foreach ($text as $token) {
            foreach ($columns as &$column) {
                if (abs($column[0]->x - $token->x) <= self::COLUMN_TOLERANCE) {
                    $column[] = $token;

                    continue 2;
                }
            }
            unset($column);
            $columns[] = [$token];
        }

        $blocks = [];
        foreach ($columns as $column) {
            usort($column, fn (SvgToken $a, SvgToken $b): int => $b->y <=> $a->y);
            $current = [];
            foreach ($column as $token) {
                if ($current !== [] && end($current)->y - $token->y > self::LINE_GAP) {
                    $blocks[] = $current;
                    $current = [];
                }
                $current[] = $token;
            }
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * Řádek končící „|“ pokračuje nejbližším tokenem vpravo na stejném účaří („500 g |“ +
     * „100 g 3,98 Kč“) — připojí ho a pokračování z dalších sloupců vyřadí. Výsledek je
     * seřazený zleva doprava.
     *
     * @param  list<SvgToken>  $text
     * @return list<SvgToken>
     */
    private function joinContinuations(array $text): array
    {
        usort($text, fn (SvgToken $a, SvgToken $b): int => $a->x <=> $b->x);

        $joined = [];
        $result = [];
        foreach ($text as $index => $token) {
            if (isset($joined[$index])) {
                continue;
            }

            $line = $token->text;
            while (preg_match(self::CONTINUATION_PATTERN, $line) === 1) {
                $next = null;
                foreach ($text as $otherIndex => $other) {
                    if ($otherIndex !== $index && ! isset($joined[$otherIndex]) && $other->x > $token->x
                        && $other->x - $token->x <= self::CONTINUATION_MAX_DISTANCE
                        && abs($other->y - $token->y) <= self::CONTINUATION_BASELINE_TOLERANCE
                        && ($next === null || $other->x < $text[$next]->x)) {
                        $next = $otherIndex;
                    }
                }
                if ($next === null) {
                    break;
                }

                $joined[$next] = true;
                $line = rtrim($line).' '.$text[$next]->text;
            }

            $result[] = $line === $token->text ? $token : new SvgToken($token->x, $token->y, $token->size, $token->fill, $line);
        }

        return $result;
    }

    /**
     * Řádky bloku bez „|“; cena za jednotku rozdělená na dva řádky („100g“ + „31,96 Kč“) se spojí.
     *
     * @param  Block  $block
     * @return list<string>
     */
    private function lines(array $block): array
    {
        $lines = array_map(fn (SvgToken $t): string => Text::clean(str_replace('|', ' ', $t->text)) ?? '', $block);

        $result = [];
        for ($i = 0, $count = count($lines); $i < $count; $i++) {
            if (isset($lines[$i + 1]) && preg_match(self::SPLIT_UNIT_SIZE_PATTERN, $lines[$i]) === 1
                && preg_match(self::SPLIT_UNIT_PRICE_PATTERN, $lines[$i + 1]) === 1) {
                $result[] = $lines[$i].' '.$lines[++$i];

                continue;
            }
            $result[] = $lines[$i];
        }

        return $result;
    }

    /**
     * Ověří, že blok patří k ceně, a vrátí název a balení; null, když ne.
     *
     * @param  Block  $block
     * @return Tile|null
     */
    private function matchBlock(array $block, SvgToken $anchor, int $price): ?array
    {
        $x = $block[0]->x;
        $top = $block[0]->y;
        $bottom = end($block)->y;
        if ($x < $anchor->x - self::BLOCK_LEFT || $x > $anchor->x + self::BLOCK_RIGHT
            || $bottom > $anchor->y + self::BLOCK_ABOVE || $top < $anchor->y - self::BLOCK_BELOW) {
            return null;
        }

        $lines = $this->lines($block);

        $unitLine = null;
        foreach ($lines as $i => $line) {
            if (preg_match(self::UNIT_PRICE_PATTERN, $line) === 1) {
                $unitLine = $i;
                break;
            }
        }

        if ($unitLine === null) {
            return $this->matchVariantBlock($lines, $price) ?? $this->matchSingleUnitBlock($lines, $x, $bottom, $anchor);
        }

        // Balení: řádek nebo dva nad cenou za jednotku, jinak zbytek jejího řádku
        $package = null;
        $packageLine = null;
        for ($i = $unitLine - 1; $i >= max(0, $unitLine - 2); $i--) {
            if (! str_contains($lines[$i], 'Kč') && preg_match(self::PACKAGE_PATTERN, $lines[$i], $m) === 1) {
                [$package, $packageLine] = [$m[0], $i];
                break;
            }
        }
        if ($package === null && preg_match(self::PACKAGE_PATTERN, (string) preg_replace(self::UNIT_PRICE_PATTERN, '', $lines[$unitLine]), $m) === 1) {
            [$package, $packageLine] = [$m[0], $unitLine];
        }

        if ($package === null || $packageLine === null || preg_match(self::UNIT_PRICE_PATTERN, $lines[$unitLine], $unit) !== 1
            || ! $this->unitPriceMatches($price, $package, $unit[1].' '.$unit[2], $unit[3])) {
            return null;
        }

        return [
            'name' => $this->name(array_slice($lines, 0, $packageLine), mb_substr($lines[$packageLine], 0, (int) mb_strpos($lines[$packageLine], $package))),
            'package' => $package,
            'text' => implode(' ', $lines),
        ];
    }

    /**
     * Blok s variantami „270/280 g“ + „100g 7,37/7,11 Kč“ — cena musí sedět k oběma.
     *
     * @param  list<string>  $lines
     * @return Tile|null
     */
    private function matchVariantBlock(array $lines, int $price): ?array
    {
        foreach ($lines as $unitLine => $line) {
            if (preg_match(self::VARIANT_UNIT_PATTERN, $line, $unit) !== 1) {
                continue;
            }

            // Balení na řádku ceny za jednotku nebo na jednom ze dvou řádků nad ní
            for ($i = $unitLine; $i >= max(0, $unitLine - 2); $i--) {
                $candidate = $i === $unitLine ? (string) preg_replace(self::VARIANT_UNIT_PATTERN, '', $line) : $lines[$i];
                if (preg_match(self::VARIANT_PACKAGE_PATTERN, $candidate, $package) !== 1) {
                    continue;
                }

                $unitSize = $unit[1].' '.$unit[2];
                if (! $this->unitPriceMatches($price, $package[1].' '.$package[3], $unitSize, $unit[3])
                    || ! $this->unitPriceMatches($price, $package[2].' '.$package[3], $unitSize, $unit[4])) {
                    return null;
                }

                return [
                    'name' => $this->name(array_slice($lines, 0, $i), $i === $unitLine ? '' : mb_substr($lines[$i], 0, (int) mb_strpos($lines[$i], $package[0]))),
                    'package' => $package[0],
                    'text' => implode(' ', $lines),
                ];
            }
        }

        return null;
    }

    /**
     * Blok bez ceny za jednotku — balení jedné jednotky („1l“, „cena za 1 kg“) přímo nad cenou.
     *
     * @param  list<string>  $lines
     * @return Tile|null
     */
    private function matchSingleUnitBlock(array $lines, float $x, float $bottom, SvgToken $anchor): ?array
    {
        if (abs($x - $anchor->x) > self::SINGLE_UNIT_X_TOLERANCE || $bottom <= $anchor->y || $bottom - $anchor->y > self::SINGLE_UNIT_MAX_ABOVE) {
            return null;
        }

        foreach ($lines as $i => $line) {
            $parts = array_map(trim(...), explode(' | ', str_replace('|', ' | ', $line)));
            foreach ($parts as $part) {
                if (preg_match(self::SINGLE_UNIT_PATTERN, $part, $m) === 1) {
                    $rest = trim(str_replace($m[0], '', $line));

                    return ['name' => $this->name(array_slice($lines, 0, $i), $rest), 'package' => '1 '.mb_strtolower($m[1]), 'text' => implode(' ', $lines)];
                }
            }
        }

        return null;
    }

    /**
     * Cena přepočtená na balení odpovídá uvedené ceně za jednotku?
     *
     * @param  string  $package  Balení („250 g“)
     * @param  string  $unitSize  Jednotka ceny za jednotku („100 g“)
     * @param  string  $unitPrice  Cena za jednotku v Kč („5,16“)
     */
    private function unitPriceMatches(int $price, string $package, string $unitSize, string $unitPrice): bool
    {
        $packageSize = $this->packages->parse($package);
        $unit = $this->packages->parse($unitSize);
        if ($packageSize === null || $unit === null || $packageSize->unit !== $unit->unit) {
            return false;
        }

        return UnitPriceCheck::matches($price, $packageSize->quantity, $unit->quantity, $this->prices->parse($unitPrice));
    }

    /**
     * Název z řádků nad balením — bez ceny za 30 dní („< 13,90 Kč“) a značek „*“, „<“.
     *
     * @param  list<string>  $lines
     */
    private function name(array $lines, string $rest): string
    {
        $parts = array_filter([...$lines, $rest], fn (string $line): bool => $line !== '' && preg_match(self::NOT_NAME_PATTERN, $line) !== 1);
        $name = Text::clean(str_replace(['*', '<'], '', implode(' ', $parts))) ?? '';

        // Zbytek rozděleného balení („MOZZARELLA 220/“ z „220/125g“)
        return trim((string) preg_replace('/[\s\d\/,.]+$/u', '', $name));
    }

    /**
     * Přeškrtnutá cena u velké ceny; null, když ji dlaždice nemá. Malé číslo bez přeškrtávací
     * čáry se uzná, jen když k ceně sedí procento ze štítku slevy.
     *
     * @param  list<SvgToken>  $tokens
     */
    private function crossedPrice(array $tokens, SvgToken $anchor, int $price): ?int
    {
        $unstruck = null;
        foreach ($tokens as $number) {
            if ($number->size >= self::TEXT_MAX_SIZE || preg_match(self::SMALL_PRICE_PATTERN, trim($number->text)) !== 1
                || $number->x < $anchor->x + self::CROSSED_RIGHT_MIN || $number->x > $anchor->x + self::CROSSED_RIGHT_MAX
                || $number->y > $anchor->y || $number->y < $anchor->y - self::CROSSED_BELOW) {
                continue;
            }

            if ($this->isStruck($tokens, $number)) {
                return $this->smallPrice(trim($number->text));
            }
            $unstruck ??= $this->smallPrice(trim($number->text));
        }

        $badge = $this->badgePercent($tokens, $anchor);

        return $unstruck !== null && $badge !== null && $this->matchesBadge($price, $unstruck, $badge) ? $unstruck : null;
    }

    /**
     * Má malá cena za sebou červenou přeškrtávací čáru?
     *
     * @param  list<SvgToken>  $tokens
     */
    private function isStruck(array $tokens, SvgToken $number): bool
    {
        foreach ($tokens as $strike) {
            if ($strike->fill === self::RED && preg_match(self::STRIKE_PATTERN, trim($strike->text)) === 1
                && abs($strike->y - $number->y) < self::STRIKE_BASELINE_TOLERANCE
                && $strike->x > $number->x && $strike->x < $number->x + self::STRIKE_DISTANCE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Procento ze štítku slevy nad cenou („33 %“); null, když dlaždice štítek nemá.
     *
     * @param  list<SvgToken>  $tokens
     */
    private function badgePercent(array $tokens, SvgToken $anchor): ?int
    {
        foreach ($tokens as $percent) {
            if (trim($percent->text) !== '%'
                || $percent->x - $anchor->x < self::BADGE_RIGHT_MIN || $percent->x - $anchor->x > self::BADGE_RIGHT_MAX
                || $percent->y - $anchor->y < self::BADGE_ABOVE_MIN || $percent->y - $anchor->y > self::BADGE_ABOVE_MAX) {
                continue;
            }

            foreach ($tokens as $number) {
                if (preg_match(self::BADGE_NUMBER_PATTERN, trim($number->text)) === 1
                    && abs($number->y - $percent->y) < self::BADGE_BASELINE_TOLERANCE
                    && $percent->x > $number->x && $percent->x - $number->x < self::BADGE_NUMBER_MAX_GAP) {
                    return (int) trim($number->text);
                }
            }
        }

        return null;
    }

    /**
     * Sedí sleva z původní na novou cenu k procentu ze štítku?
     */
    private function matchesBadge(int $price, int $original, int $badge): bool
    {
        return DiscountCheck::roundedWithin($price, $original, $badge, self::BADGE_TOLERANCE);
    }

    /**
     * Je u ceny drobný štítek PENNY karty?
     *
     * @param  list<SvgToken>  $tokens
     */
    private function isCardTile(array $tokens, SvgToken $anchor): bool
    {
        foreach ($tokens as $token) {
            if (abs($token->x - $anchor->x) < self::CARD_DISTANCE_X && abs($token->y - $anchor->y) < self::CARD_DISTANCE_Y
                && $token->size < self::CARD_LABEL_MAX_SIZE && preg_match(self::CARD_PATTERN, $token->text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cena bez PENNY karty u dlaždice s kartou — malé číslo vpravo pod cenou u popisku „cena bez
     * pennykarty“; null, když chybí, není vyšší, nebo nesedí ke štítku slevy.
     *
     * @param  list<SvgToken>  $tokens
     */
    private function cardRegularPrice(array $tokens, SvgToken $anchor, int $cardPrice): ?int
    {
        $labelled = array_filter($tokens, fn (SvgToken $t): bool => abs($t->x - $anchor->x) < self::CARD_REGULAR_LABEL_DISTANCE_X
            && abs($t->y - $anchor->y) < self::CARD_REGULAR_LABEL_DISTANCE_Y && preg_match(self::CARD_REGULAR_LABEL_PATTERN, $t->text) === 1);
        if ($labelled === []) {
            return null;
        }

        foreach ($tokens as $number) {
            if ($number->size < self::TEXT_MAX_SIZE && preg_match(self::SMALL_PRICE_PATTERN, trim($number->text)) === 1
                && $number->x >= $anchor->x + self::CROSSED_RIGHT_MIN && $number->x <= $anchor->x + self::CROSSED_RIGHT_MAX
                && $number->y <= $anchor->y && $number->y >= $anchor->y - self::CARD_REGULAR_BELOW) {
                $regular = $this->smallPrice(trim($number->text));
                $badge = $this->badgePercent($tokens, $anchor);

                return $regular > $cardPrice && ($badge === null || $this->matchesBadge($cardPrice, $regular, $badge)) ? $regular : null;
            }
        }

        return null;
    }

    /**
     * Je u ceny značka ceny za více kusů („při koupi 2 a více ks“)?
     *
     * @param  list<SvgToken>  $tokens
     */
    private function hasMultibuyMarker(array $tokens, SvgToken $anchor): bool
    {
        foreach ($tokens as $token) {
            if (abs($token->x - $anchor->x) < self::MULTIBUY_MARKER_DISTANCE_X && abs($token->y - $anchor->y) < self::MULTIBUY_MARKER_DISTANCE_Y
                && preg_match(self::MULTIBUY_MARKER_PATTERN, trim($token->text)) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cena kusu a text akce na více kusů z bloku; null, když blok ceny neuvádí nebo cena za
     * více kusů nesedí k velké ceně.
     *
     * @return array{price: int, text: string}|null
     */
    private function multibuy(string $text, int $bulkPrice): ?array
    {
        if (preg_match(self::MULTIBUY_PATTERN, $text, $m) !== 1) {
            return null;
        }

        $single = $this->prices->parse($m[1]);
        if ($this->prices->parse($m[3]) !== $bulkPrice || $single <= $bulkPrice) {
            return null;
        }

        return ['price' => $single, 'text' => (Text::clean($m[2]) ?? $m[2]).self::MULTIBUY_PRICE_SEPARATOR.$this->formatter->format($bulkPrice)];
    }

    /**
     * Nabídka z ověřené dlaždice; null, když jde o cenu za více kusů, kterou nejde přečíst.
     *
     * @param  Tile  $tile
     * @param  Anchor  $anchor
     * @param  list<SvgToken>  $tokens
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function offer(array $tile, array $anchor, array $tokens, array $validity, int $page, string $pageUrl): ?OfferData
    {
        $price = $anchor['price'];
        $original = null;
        $loyalty = null;
        $promotion = null;

        if ($anchor['regular'] !== null) {
            // Velká cena je cena s kartou, běžná cena vedle ní (R19)
            [$offerType, $loyalty, $price] = [OfferType::LoyaltyOnly, $price, $anchor['regular']];
        } elseif ($this->hasMultibuyMarker($tokens, $anchor['token'])) {
            $multibuy = $this->multibuy($tile['text'], $price);
            if ($multibuy === null) {
                return null;
            }
            [$offerType, $price, $promotion] = [OfferType::Multibuy, $multibuy['price'], $multibuy['text']];
        } else {
            $crossed = $this->crossedPrice($tokens, $anchor['token'], $price);
            $original = $crossed !== null && $crossed > $price ? $crossed : null;
            $offerType = $original !== null ? OfferType::Discount : OfferType::PromoPrice;
        }

        return new OfferData(
            externalId: self::EXTERNAL_ID_PREFIX.substr(sha1(mb_strtolower($tile['name']).'|'.$tile['package'].'|'.$price), 0, self::EXTERNAL_ID_LENGTH),
            name: $tile['name'],
            offerType: $offerType,
            validFrom: $validity[0],
            validTo: $validity[1],
            raw: ['page' => $page, 'x' => round($anchor['token']->x, 1), 'y' => round($anchor['token']->y, 1), 'name' => $tile['name'], 'package' => $tile['package'], 'price' => $price, 'crossed' => $original, 'loyalty' => $loyalty, 'promotion' => $promotion],
            price: $price,
            originalPrice: $original,
            loyaltyPrice: $loyalty,
            loyaltyProgram: $loyalty === null ? null : LoyaltyProgram::PennyKarta,
            promotionText: $promotion,
            variantNote: $this->variants->detect($tile['name']),
            packageText: $tile['package'],
            package: $this->packages->parse($tile['package']),
            sourceUrl: $pageUrl,
        );
    }
}
