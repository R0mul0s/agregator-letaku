<?php

/**
 * Nabídky z vektorové vrstvy letáku Penny — bez LLM (R23, ZDROJE_DAT.md, Penny).
 *
 * Postup na stránce:
 * 1. **Ceny:** velké číslo s glyfem „Ǻ“ (= „,90“), červené nebo bílé.
 * 2. **Bloky textu:** řádky zarovnané pod sebou (stejné x, mezery do ~10 bodů) — název,
 *    upřesnění, balení a cena za jednotku jedné dlaždice.
 * 3. **Přiřazení ceny k bloku je ověřené:** cena přepočtená na balení musí dát cenu za
 *    jednotku uvedenou v bloku („250 g“ + „100 g 5,16 Kč“ ↔ 12,90 Kč). U balení 1 kg / 1 l /
 *    1 ks („cena za 1 kg“) cena za jednotku chybí, pak musí blok ležet přímo nad cenou.
 *    Každý blok patří nejvýš jedné ceně. Co se ověřit nedá, se neuloží — chybějící
 *    nabídka je lepší než nabídka se špatnou cenou.
 * 4. **Přeškrtnutá cena:** malé číslo vpravo pod cenou, za ním „,“ + glyfy haléřů
 *    (U+E00A U+E009 = „90“) a červená přeškrtávací čára (U+E00F / E010 / E011 podle délky).
 * 5. Dlaždice s PENNY kartou se přeskočí — nese je API (PennyApiParser).
 * 6. Platnost: „platí od … do …“ na stránce, jinak „Nabídka platná od … do …“, jinak
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
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

final class PennyLeafletParser
{
    private const RED = 'rgb(255,45,22)';

    private const WHITE = 'rgb(255,255,255)';

    /** Velká cena „24Ǻ“ = 24,90 Kč (glyf Ǻ U+01FA je „,90“). */
    private const BIG_PRICE_PATTERN = '/^(\d{1,4})\x{01FA}$/u';

    private const HALERS_PER_CROWN = 100;

    /** Haléře ve velké ceně i v malých cenách — v letáku jsou všechny ceny ,90. */
    private const PRICE_FRACTION = 90;

    /** Velikost písma velké ceny. */
    private const BIG_PRICE_MIN_SIZE = 15.0;

    /** Velikost písma textu dlaždice (název, balení, cena za jednotku). */
    private const TEXT_MIN_SIZE = 4.0;

    private const TEXT_MAX_SIZE = 10.0;

    /** Odchylka x řádků jednoho bloku a největší mezera mezi řádky. */
    private const COLUMN_TOLERANCE = 3.0;

    private const LINE_GAP = 10.0;

    /** Kde blok vůči ceně hledat (body stránky). */
    private const BLOCK_LEFT = 90.0;

    private const BLOCK_RIGHT = 70.0;

    private const BLOCK_ABOVE = 90.0;

    private const BLOCK_BELOW = 30.0;

    /** Blok bez ceny za jednotku musí ležet přímo nad cenou. */
    private const SINGLE_UNIT_X_TOLERANCE = 15.0;

    private const SINGLE_UNIT_MAX_ABOVE = 110.0;

    /** Tolerance kontroly ceny za jednotku — haléře a podíl (zaokrouhlení obchodu). */
    private const UNIT_CHECK_HALERS = 2;

    private const UNIT_CHECK_RATIO = 0.015;

    /** Přeškrtnutá cena vůči velké ceně. */
    private const CROSSED_RIGHT_MIN = 5.0;

    private const CROSSED_RIGHT_MAX = 40.0;

    private const CROSSED_BELOW = 12.0;

    private const STRIKE_DISTANCE = 25.0;

    private const STRIKE_PATTERN = '/^[\x{E00F}\x{E010}\x{E011}]$/u';

    /** Okolí ceny, ve kterém nápis „KARTA“ znamená dlaždici s PENNY kartou. */
    private const CARD_DISTANCE_X = 90.0;

    private const CARD_DISTANCE_Y = 60.0;

    private const CARD_PATTERN = '/karta|pennykart/iu';

    /** Cena za jednotku v bloku: „100 g 5,16 Kč“, „1l 14,60Kč“, „1 kg = 39,80 Kč“. */
    private const UNIT_PRICE_PATTERN = '/(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l|ks)\s*(?:=\s*)?(\d+[.,]\d{2})\s*Kč/iu';

    /** Balení v řádku bloku: „250 g“, „1,5l“, „40ks“, „2 x 250 ml“. */
    private const PACKAGE_PATTERN = '/(?:(\d+)\s*[x×]\s*)?(\d+(?:[.,]\d+)?)\s*(kg|ks|ml|g|l)(?![\p{L}\d])/iu';

    /** Balení jedné jednotky bez ceny za jednotku: „1l“, „1 kg“, „cena za 1 kg“. */
    private const SINGLE_UNIT_PATTERN = '/(?:^|cena za\s*)1\s*(kg|l|ks)\s*$/iu';

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
     * Ověřené nabídky stránky.
     *
     * @param  list<SvgToken>  $tokens
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     * @return list<OfferData>
     */
    public function offers(array $tokens, array $validity, int $page, string $pageUrl): array
    {
        $blocks = $this->blocks($tokens);
        $candidates = [];

        foreach ($tokens as $anchor) {
            $price = $this->bigPrice($anchor);
            if ($price === null || $this->isCardTile($tokens, $anchor)) {
                continue;
            }

            foreach ($blocks as $index => $block) {
                $tile = $this->matchBlock($block, $anchor, $price);
                if ($tile !== null && preg_match(self::NAME_PATTERN, $tile['name']) === 1) {
                    $candidates[] = [
                        'anchor' => $anchor,
                        'block' => $index,
                        'price' => $price,
                        'tile' => $tile,
                        'distance' => hypot($block[0]->x - $anchor->x, end($block)->y - $anchor->y),
                    ];
                }
            }
        }

        // Každá cena i každý blok nejvýš jednou — nejbližší dvojice vyhrávají
        usort($candidates, fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);
        $usedAnchors = [];
        $usedBlocks = [];
        $offers = [];
        foreach ($candidates as $candidate) {
            $anchorId = spl_object_id($candidate['anchor']);
            if (isset($usedAnchors[$anchorId]) || isset($usedBlocks[$candidate['block']])) {
                continue;
            }

            $usedAnchors[$anchorId] = true;
            $usedBlocks[$candidate['block']] = true;
            $offers[] = $this->offer($candidate['tile'], $candidate['price'], $this->crossedPrice($tokens, $candidate['anchor']), $validity, $page, $pageUrl);
        }

        return $offers;
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

        return (int) $m[1] * self::HALERS_PER_CROWN + self::PRICE_FRACTION;
    }

    /**
     * Bloky textu: řádky se stejným x pod sebou, shora dolů.
     *
     * @param  list<SvgToken>  $tokens
     * @return list<non-empty-list<SvgToken>>
     */
    private function blocks(array $tokens): array
    {
        $text = array_filter($tokens, fn (SvgToken $t): bool => $t->fill !== self::RED
            && $t->size >= self::TEXT_MIN_SIZE && $t->size <= self::TEXT_MAX_SIZE && trim($t->text) !== '');
        usort($text, fn (SvgToken $a, SvgToken $b): int => $a->x <=> $b->x);

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
     * Ověří, že blok patří k ceně, a vrátí název a balení; null, když ne.
     *
     * @param  non-empty-list<SvgToken>  $block
     * @return array{name: string, package: string}|null
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

        $lines = array_map(fn (SvgToken $t): string => Text::clean(str_replace('|', ' ', $t->text)) ?? '', $block);

        $unitLine = null;
        foreach ($lines as $i => $line) {
            if (preg_match(self::UNIT_PRICE_PATTERN, $line) === 1) {
                $unitLine = $i;
                break;
            }
        }

        if ($unitLine === null) {
            return $this->matchSingleUnitBlock($lines, $x, $bottom, $anchor);
        }

        // Balení: řádek nebo dva nad cenou za jednotku, jinak zbytek jejího řádku
        $package = null;
        $packageLine = null;
        for ($i = $unitLine - 1; $i >= max(0, $unitLine - 2); $i--) {
            if (! str_contains($lines[$i], 'Kč') && preg_match(self::PACKAGE_PATTERN, $lines[$i], $m) === 1) {
                [$package, $packageLine] = [$m, $i];
                break;
            }
        }
        if ($package === null && preg_match(self::PACKAGE_PATTERN, (string) preg_replace(self::UNIT_PRICE_PATTERN, '', $lines[$unitLine]), $m) === 1) {
            [$package, $packageLine] = [$m, $unitLine];
        }

        preg_match(self::UNIT_PRICE_PATTERN, $lines[$unitLine], $unit);
        if ($package === null || $packageLine === null || ! $this->unitPriceMatches($price, $package, $unit)) {
            return null;
        }

        return [
            'name' => $this->name(array_slice($lines, 0, $packageLine), mb_substr($lines[$packageLine], 0, (int) mb_strpos($lines[$packageLine], $package[0]))),
            'package' => $package[0],
        ];
    }

    /**
     * Blok bez ceny za jednotku — balení jedné jednotky („1l“, „cena za 1 kg“) přímo nad cenou.
     *
     * @param  list<string>  $lines
     * @return array{name: string, package: string}|null
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

                    return ['name' => $this->name(array_slice($lines, 0, $i), $rest), 'package' => '1 '.mb_strtolower($m[1])];
                }
            }
        }

        return null;
    }

    /**
     * Cena přepočtená na balení odpovídá uvedené ceně za jednotku?
     *
     * @param  array<int, string>  $package  Shoda PACKAGE_PATTERN
     * @param  array<int, string>  $unit  Shoda UNIT_PRICE_PATTERN
     */
    private function unitPriceMatches(int $price, array $package, array $unit): bool
    {
        $packageSize = $this->packages->parse($package[0]);
        $unitSize = $this->packages->parse($unit[1].' '.$unit[2]);
        if ($packageSize === null || $unitSize === null || $packageSize->unit !== $unitSize->unit) {
            return false;
        }

        $expected = (int) round($price * $unitSize->quantity / $packageSize->quantity);
        $stated = (int) round((float) str_replace(',', '.', $unit[3]) * self::HALERS_PER_CROWN);

        return abs($expected - $stated) <= max(self::UNIT_CHECK_HALERS, $stated * self::UNIT_CHECK_RATIO);
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
     * Přeškrtnutá cena u velké ceny; null, když ji dlaždice nemá.
     *
     * @param  list<SvgToken>  $tokens
     */
    private function crossedPrice(array $tokens, SvgToken $anchor): ?int
    {
        foreach ($tokens as $number) {
            if ($number->size >= self::TEXT_MAX_SIZE || preg_match('/^\d{1,4}$/', trim($number->text)) !== 1
                || $number->x < $anchor->x + self::CROSSED_RIGHT_MIN || $number->x > $anchor->x + self::CROSSED_RIGHT_MAX
                || $number->y > $anchor->y || $number->y < $anchor->y - self::CROSSED_BELOW) {
                continue;
            }

            foreach ($tokens as $strike) {
                if ($strike->fill === self::RED && preg_match(self::STRIKE_PATTERN, trim($strike->text)) === 1
                    && abs($strike->y - $number->y) < 2 && $strike->x > $number->x && $strike->x < $number->x + self::STRIKE_DISTANCE) {
                    return (int) trim($number->text) * self::HALERS_PER_CROWN + self::PRICE_FRACTION;
                }
            }
        }

        return null;
    }

    /**
     * Je u ceny nápis PENNY karty?
     *
     * @param  list<SvgToken>  $tokens
     */
    private function isCardTile(array $tokens, SvgToken $anchor): bool
    {
        foreach ($tokens as $token) {
            if (abs($token->x - $anchor->x) < self::CARD_DISTANCE_X && abs($token->y - $anchor->y) < self::CARD_DISTANCE_Y
                && preg_match(self::CARD_PATTERN, $token->text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nabídka z ověřené dlaždice.
     *
     * @param  array{name: string, package: string}  $tile
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function offer(array $tile, int $price, ?int $crossed, array $validity, int $page, string $pageUrl): OfferData
    {
        $isDiscount = $crossed !== null && $crossed > $price;
        $package = $this->packages->parse($tile['package']);

        return new OfferData(
            externalId: self::EXTERNAL_ID_PREFIX.substr(sha1(mb_strtolower($tile['name']).'|'.$tile['package'].'|'.$price), 0, self::EXTERNAL_ID_LENGTH),
            name: $tile['name'],
            offerType: $isDiscount ? OfferType::Discount : OfferType::PromoPrice,
            validFrom: $validity[0],
            validTo: $validity[1],
            raw: ['page' => $page, 'name' => $tile['name'], 'package' => $tile['package'], 'price' => $price, 'crossed' => $crossed],
            price: $price,
            originalPrice: $isDiscount ? $crossed : null,
            variantNote: $this->variants->detect($tile['name']),
            packageText: $tile['package'],
            package: $package,
            sourceUrl: $pageUrl,
        );
    }
}
