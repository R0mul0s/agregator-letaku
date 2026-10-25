<?php

/**
 * Akce s cenou z PDF letáků COOP — text s polohou z `pdftotext -bbox-layout` (R86, R167), bez LLM.
 *
 * Letáky s textovou vrstvou má jen coopclub.cz pro Čechy: celostátní a čtyři regionální
 * (ZDROJE_DAT.md, Coop). Dlaždice:
 * - **text** je jeden blok pdftotext: značka verzálkami (~11,5 b., „MADETA“), název tučně (~12,2 b.),
 *   upřesnění (~11,5 b., „vybrané druhy“) a řádek balení s cenou za jednotku „130 g | 1 kg = 130,–“;
 *   pod ním malým písmem (~8,7 b.) výrobce, záloha, u pultu „1 kg = 219,– | pultový prodej“ a u ceny
 *   s aplikací „s kupónem 1 l = 17,45“ / „bez kupónu 1 l = 18,45“ (někdy samostatný blok těsně pod);
 * - **cena** u obrázku: koruny a haléře s čárkou jako dvě slova („34“ „,90“, celé koruny „219“ „,–“);
 *   u ceny s aplikací velká „cena s kupónem v aplikaci“ a menší „CENA BEZ APLIKACE“.
 *
 * Leták nemá přeškrtnuté ceny ani slevy — všechny akce jsou akční cena bez slevy (R8), cena s kupónem
 * v aplikaci jde do `loyalty_price`. Cena se přijme jen **ověřená** cenou za jednotku (R26), u kupónu obě
 * ceny; ze sedících dvojic vyhrávají nejbližší. Pult s cenou za 100 g (odznak „100 g“ u ceny, v textu jen
 * „1 kg = 219,–“) ověří desetinásobek ceny.
 *
 * Platnost: z hlavičky první strany („OD STŘEDY 21. 10. DO ÚTERÝ 3. 11. 2026“, celostátní „od 21. 10.
 * do 3. 11. 2026“). Kde leták platí (region), rozhoduje zdroj.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Coop;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\VariantNote;
use App\Domain\Sources\Pdf\CountsTiles;
use App\Domain\Sources\Pdf\LeafletOfferId;
use App\Domain\Sources\Pdf\PdfBox;
use App\Domain\Sources\Pdf\PdfLayout;
use App\Domain\Sources\Pdf\PdfLine;
use App\Domain\Sources\Pdf\PdfPage;
use App\Domain\Sources\Pdf\PdfWord;
use App\Domain\Sources\Pdf\TileUnits;
use App\Domain\Sources\Pdf\UnitPriceCheck;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type UnitPrice array{kind: string|null, unit: string, quantity: float, values: list<int>}
 * @phpstan-type Tile array{lines: list<PdfLine>, box: PdfBox}
 */
final class CoopLeafletParser
{
    use CountsTiles;

    /** Koruny ceny (body PDF): menší cena bez aplikace ~16, běžné ceny ~25–33. */
    private const PRICE_MIN_HEIGHT = 12.0;

    /** Haléře „,90“ vpravo od korun (mezera v bodech) a nahoře (podíl výšky korun). */
    private const FRACTION_MIN_GAP = -3.0;

    private const FRACTION_MAX_GAP = 4.0;

    private const FRACTION_TOP_RATIO = 0.4;

    private const FRACTION_TOP_TOLERANCE = 2.0;

    /**
     * Velikost písma se liší po letácích (regionální název 12,2 b., celostátní 16,8, JČ 10,7) — řádky se
     * třídí podle největšího písma bloku: tučný název ho má (upřesnění a značka o ~0,7 b. méně), cena
     * za jednotku a výrobce mají méně než tento podíl.
     */
    private const TEXT_MIN_RATIO = 0.85;

    /** Tučný řádek názvu: nejvýš o tolik bodů menší než největší písmo bloku. */
    private const NAME_HEIGHT_TOLERANCE = 0.3;

    /** Řádek textu dlaždice má písmo menší než cena (cena ~19 b. a víc, i ve stejném bloku). */
    private const TEXT_MAX_HEIGHT = 18.0;

    /** Blok s menším písmem těsně pod blokem textu patří k němu (zarovnání a mezera v bodech). */
    private const BLOCK_ALIGN_TOLERANCE = 3.0;

    private const BLOCK_MAX_GAP = 4.0;

    private const BLOCK_MAX_OVERLAP = 3.5;

    /** Největší vzdálenost ceny od textu dlaždice. */
    private const TILE_MAX_DISTANCE = 260.0;

    /** Cena bez aplikace leží u ceny s kupónem. */
    private const REGULAR_MAX_DISTANCE = 80.0;

    /** Pult: cena za 100 g, cena za jednotku je za 1 kg. */
    private const HUNDRED_GRAMS = 100.0;

    private const HUNDRED_GRAMS_TEXT = '100 g';

    private const GRAMS = 'g';

    private const KILOGRAM_GRAMS = 1000.0;

    private const CROWNS_PATTERN = '/^\d{1,4}$/';

    /** Haléře s čárkou („,90“), celé koruny „,–“. */
    private const FRACTION_PATTERN = '/^,(\d{2}|[–-])$/u';

    /** Hlavička: „OD STŘEDY 21. 10. DO ÚTERÝ 3. 11. 2026“, „od 21. 10. do 3. 11. 2026“. */
    private const VALIDITY_PATTERN = '/od\s+(?:\p{L}+\s+)?(\d{1,2})\.\s*(\d{1,2})\.\s+do\s+(?:\p{L}+\s+)?(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/iu';

    /**
     * Cena za jednotku: „1 kg = 130,–“, „1 l = 25,80“, víc velikostí „1 kg = 290,83/268,46“, s aplikací
     * „s kupónem 1 l = 17,45“ / „bez kupónu 1 l = 18,45“. Skupiny: druh, množství, jednotka, ceny.
     */
    private const UNIT_PRICE_PATTERN = '/(?:(s\s+kupónem|bez\s+kupónu)\s+)?(\d+(?:,\d+)?)\s*(\p{L}+)\s*=\s*(\d{1,3}(?:\s\d{3})*,(?:\d{1,2}|[–-])(?:\s*\/\s*\d{1,3}(?:\s\d{3})*,(?:\d{1,2}|[–-]))*)/iu';

    private const UNIT_VALUES_SEPARATOR = '/\s*\/\s*/u';

    private const WHOLE_CROWNS_PATTERN = '/,[–-]$/u';

    /** Mezera v tisících („1 495,–“). */
    private const THOUSANDS_PATTERN = '/(?<=\d)\s(?=\d{3}\b)/u';

    /** Řádek jen s cenou („34 ,90“) do textu dlaždice nepatří. */
    private const PRICE_LINE_PATTERN = '/^[\d\s,–-]+$/u';

    /** Řádek s písmeny (značka, název). */
    private const LETTERS_PATTERN = '/\p{L}/u';

    /** Cena za jednotku k ceně s kupónem; „bez kupónu“ i bez označení je k běžné ceně. */
    private const KIND_COUPON = 's kupónem';

    /** Balení na začátku řádku: „130 g“, „4 × 85 g“, „120/130 g“, „16/47/59 PD“, „1,4 kg“. */
    private const PACKAGE_LINE_PATTERN = '/^(?:\d+\s*[×x]\s*)?\d+(?:,\d+)?(?:\s*\/\s*\d+(?:,\d+)?)*\s*\p{L}+/u';

    /** Oddělovač balení a ceny za jednotku v řádku („130 g | 1 kg = 130,–“). */
    private const PART_SEPARATOR = '|';

    /** Rozdělení slova na konci řádku názvu. */
    private const HYPHEN_PATTERN = '/[-‐‑]$/u';

    private const WHITESPACE_PATTERN = '/\s+/u';

    public function __construct(
        private readonly PriceParser $priceParser,
        private readonly PackageParser $packages,
        private readonly VariantNote $variants,
        private readonly LeafletDates $dates,
        private readonly LeafletOfferId $ids,
    ) {}

    /**
     * Platnost letáku z hlavičky první strany; null, když tam není.
     *
     * @param  list<PdfPage>  $pages
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function validity(array $pages): ?array
    {
        $text = implode(' ', array_map(fn (PdfLine $line): string => $line->text(), $pages[0]->lines ?? []));
        if (preg_match(self::VALIDITY_PATTERN, $text, $m) !== 1) {
            return null;
        }

        return $this->dates->range((int) $m[1], (int) $m[2], null, (int) $m[3], (int) $m[4], (int) $m[5]);
    }

    /**
     * Ověřené akce celého letáku; stejná akce na více stranách se vrátí jednou — z první strany.
     *
     * @param  list<PdfPage>  $pages
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     * @return list<OfferData>
     */
    public function offers(array $pages, array $validity, string $sourceUrl): array
    {
        $this->resetTileCount();
        $offers = [];
        foreach ($pages as $page) {
            foreach ($this->pageOffers($page, $validity, $sourceUrl) as $offer) {
                $offers[$offer->key()] ??= $offer;
            }
        }

        return array_values($offers);
    }

    /**
     * Ověřené akce jedné stránky.
     *
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     * @return list<OfferData>
     */
    private function pageOffers(PdfPage $page, array $validity, string $sourceUrl): array
    {
        $prices = $this->prices($page->words());
        $tiles = $this->tiles($page);

        $pairs = PdfLayout::greedyPairs(
            $tiles,
            $prices,
            function (array $tile, PdfBox $price) use ($prices): ?array {
                $distance = $price->distanceTo($tile['box']);
                $verified = $distance > self::TILE_MAX_DISTANCE ? null : $this->verify($price, $prices, $tile);

                return $verified === null ? null : [$distance, $verified];
            },
            // Cena bez aplikace u ceny s kupónem patří k téže dlaždici
            fn (array $verified): array => $verified['regularIndex'] === null ? [] : [$verified['regularIndex']],
        );

        $offers = [];
        foreach ($pairs as [$tileIndex, , $verified]) {
            $offers[] = $this->offer($tiles[$tileIndex], $verified, $validity, $page->number, $sourceUrl);
        }
        $this->countTiles(count($prices), count($offers));

        return $offers;
    }

    /**
     * Ceny na stránce: koruny a vedle nich nahoře haléře s čárkou („34“ „,90“, „219“ „,–“).
     *
     * @param  list<PdfWord>  $words
     * @return list<PdfBox>
     */
    private function prices(array $words): array
    {
        $fractions = array_values(array_filter($words, fn (PdfWord $word): bool => preg_match(self::FRACTION_PATTERN, $word->text) === 1));

        $prices = [];
        foreach ($words as $crowns) {
            if ($crowns->height() < self::PRICE_MIN_HEIGHT || preg_match(self::CROWNS_PATTERN, $crowns->text) !== 1) {
                continue;
            }

            foreach ($fractions as $fraction) {
                $gap = $fraction->xMin - $crowns->xMax;
                $top = $fraction->yMin - $crowns->yMin;
                if ($fraction->height() >= $crowns->height()
                    || $gap < self::FRACTION_MIN_GAP || $gap > self::FRACTION_MAX_GAP
                    || $top < -self::FRACTION_TOP_TOLERANCE || $top > $crowns->height() * self::FRACTION_TOP_RATIO) {
                    continue;
                }

                $prices[] = new PdfBox(
                    $crowns->text.$fraction->text,
                    $this->money($crowns->text.$fraction->text),
                    $crowns->xMin, $crowns->yMin, max($crowns->xMax, $fraction->xMax), max($crowns->yMax, $fraction->yMax),
                );

                break;
            }
        }

        return $prices;
    }

    /**
     * Haléře z ceny letáku; „219,–“ jsou celé koruny.
     */
    private function money(string $text): int
    {
        return $this->priceParser->parse((string) preg_replace([self::WHOLE_CROWNS_PATTERN, self::THOUSANDS_PATTERN], [',00', ''], $text));
    }

    /**
     * Dlaždice z bloků pdftotext: blok s řádkem názvu (velké písmo) a cenou za jednotku. Blok jen s malým
     * písmem těsně pod jiným blokem (výrobce, cena za jednotku pultu, ceny s kupónem) k němu patří.
     *
     * @return list<Tile>
     */
    private function tiles(PdfPage $page): array
    {
        $blocks = [];
        foreach ($page->lines as $line) {
            $blocks[$line->block][] = $line;
        }

        /** @var list<Tile> $tiles */
        $tiles = [];
        foreach ($blocks as $lines) {
            $lines = array_values(array_filter($lines, fn (PdfLine $line): bool => $line->height() <= self::TEXT_MAX_HEIGHT
                && preg_match(self::PRICE_LINE_PATTERN, $line->text()) !== 1));
            if ($lines === []) {
                continue;
            }

            $box = $this->box($lines);
            $above = $this->blockAbove($tiles, $box, $this->textHeight($lines));
            if ($above !== null) {
                $tiles[$above] = ['lines' => [...$tiles[$above]['lines'], ...$lines], 'box' => $tiles[$above]['box']->merge($box)];
            } else {
                $tiles[] = ['lines' => $lines, 'box' => $box];
            }
        }

        return array_values(array_filter($tiles, fn (array $tile): bool => $this->unitPrices($tile) !== []
            && array_any($tile['lines'], fn (PdfLine $line): bool => $this->isTextLine($line, $tile))));
    }

    /**
     * Index bloku textu, pod kterým blok s menším písmem těsně leží (zarovnaný vlevo); null, když žádný.
     *
     * @param  list<Tile>  $tiles
     */
    private function blockAbove(array $tiles, PdfBox $box, float $height): ?int
    {
        foreach ($tiles as $index => $tile) {
            $gap = $box->yMin - $tile['box']->yMax;
            if (abs($box->xMin - $tile['box']->xMin) <= self::BLOCK_ALIGN_TOLERANCE && $gap >= -self::BLOCK_MAX_OVERLAP && $gap <= self::BLOCK_MAX_GAP
                && $height < $this->textHeight($tile['lines']) * self::TEXT_MIN_RATIO) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Největší písmo řádků s písmeny (velikost názvu); 0 bez nich.
     *
     * @param  list<PdfLine>  $lines
     */
    private function textHeight(array $lines): float
    {
        $heights = array_map(fn (PdfLine $line): float => $line->height(), array_filter($lines, fn (PdfLine $line): bool => preg_match(self::LETTERS_PATTERN, $line->text()) === 1));

        return $heights === [] ? 0.0 : max($heights);
    }

    /**
     * Je řádek textem dlaždice (název, upřesnění, balení), ne cenou za jednotku nebo výrobcem?
     *
     * @param  Tile  $tile
     */
    private function isTextLine(PdfLine $line, array $tile): bool
    {
        return preg_match(self::LETTERS_PATTERN, $line->text()) === 1 && $line->height() >= $this->textHeight($tile['lines']) * self::TEXT_MIN_RATIO;
    }

    /**
     * Obdélník řádků.
     *
     * @param  non-empty-list<PdfLine>  $lines
     */
    private function box(array $lines): PdfBox
    {
        return new PdfBox(
            '',
            0,
            min(array_map(fn (PdfLine $line): float => $line->xMin, $lines)),
            min(array_map(fn (PdfLine $line): float => $line->yMin, $lines)),
            max(array_map(fn (PdfLine $line): float => $line->xMax, $lines)),
            max(array_map(fn (PdfLine $line): float => $line->yMax, $lines)),
        );
    }

    /**
     * Ceny za jednotku dlaždice (s druhem „s kupónem“ / „bez kupónu“, jinak null).
     *
     * @param  Tile  $tile
     * @return list<UnitPrice>
     */
    private function unitPrices(array $tile): array
    {
        preg_match_all(self::UNIT_PRICE_PATTERN, $this->text($tile), $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $unitPrices = [];
        foreach ($matches as $m) {
            $unit = TileUnits::unit((string) $m[3]);
            if ($unit !== null) {
                $unitPrices[] = [
                    // Celostátní leták verzálkami („S KUPÓNEM 1 PD = …“)
                    'kind' => $m[1] === null ? null : mb_strtolower((string) preg_replace(self::WHITESPACE_PATTERN, ' ', $m[1])),
                    'unit' => $unit[0],
                    'quantity' => TileUnits::number((string) $m[2]) * $unit[1],
                    'values' => array_map($this->money(...), preg_split(self::UNIT_VALUES_SEPARATOR, (string) $m[4]) ?: []),
                ];
            }
        }

        return $unitPrices;
    }

    /**
     * Celý text dlaždice jedním řádkem.
     *
     * @param  Tile  $tile
     */
    private function text(array $tile): string
    {
        return implode(' ', array_map(fn (PdfLine $line): string => $line->text(), $tile['lines']));
    }

    /**
     * Balení z řádků velkým písmem (bez cen za jednotku) v jednotkách pro porovnání.
     *
     * @param  Tile  $tile
     * @return array<string, list<float>>
     */
    private function packagesOf(array $tile): array
    {
        $parts = $this->parts($tile);

        return $parts['package'] === null ? [] : TileUnits::packages($parts['package']);
    }

    /**
     * Ověří cenu proti dlaždici. S kupónem je cena ta s kupónem a běžná cena „bez aplikace“ je jiná cena
     * blízko ní; obě musí dát svou cenu za jednotku.
     *
     * @param  list<PdfBox>  $prices
     * @param  Tile  $tile
     * @return array{price: int, loyalty: int|null, package: string|null, regularIndex: int|null}|null
     */
    private function verify(PdfBox $price, array $prices, array $tile): ?array
    {
        $unitPrices = $this->unitPrices($tile);
        $packages = $this->packagesOf($tile);
        $coupon = array_values(array_filter($unitPrices, fn (array $unitPrice): bool => $unitPrice['kind'] === self::KIND_COUPON));
        $regular = array_values(array_filter($unitPrices, fn (array $unitPrice): bool => $unitPrice['kind'] !== self::KIND_COUPON));

        // Pult: odznak „100 g“ u ceny, v textu jen cena za kilogram
        if ($packages === [] && $coupon === [] && count($regular) === 1 && $regular[0]['unit'] === self::GRAMS
            && $regular[0]['quantity'] === self::KILOGRAM_GRAMS
            && UnitPriceCheck::matches($price->value, self::HUNDRED_GRAMS, self::KILOGRAM_GRAMS, $regular[0]['values'][0] ?? 0)) {
            return ['price' => $price->value, 'loyalty' => null, 'package' => self::HUNDRED_GRAMS_TEXT, 'regularIndex' => null];
        }

        if ($coupon === []) {
            return $this->matchesAll($price->value, $regular, $packages) ? ['price' => $price->value, 'loyalty' => null, 'package' => null, 'regularIndex' => null] : null;
        }

        if (! $this->matchesAll($price->value, $coupon, $packages)) {
            return null;
        }
        foreach ($prices as $index => $candidate) {
            if ($candidate !== $price && $candidate->value > $price->value && $price->distanceTo($candidate) <= self::REGULAR_MAX_DISTANCE
                && $this->matchesAll($candidate->value, $regular, $packages)) {
                return ['price' => $candidate->value, 'loyalty' => $price->value, 'package' => null, 'regularIndex' => $index];
            }
        }

        return null;
    }

    /**
     * Dá cena každou uvedenou cenu za jednotku pro některé z balení? Víc cen za jednotku odděluje
     * lomítko u víc velikostí balení („120/130 g | 1 kg = 290,83/268,46“).
     *
     * @param  list<UnitPrice>  $unitPrices
     * @param  array<string, list<float>>  $packages
     */
    private function matchesAll(int $price, array $unitPrices, array $packages): bool
    {
        return $unitPrices !== [] && array_all($unitPrices, fn (array $unitPrice): bool => array_all(
            $unitPrice['values'],
            fn (int $value): bool => UnitPriceCheck::matchesAny($price, $value, $unitPrice['quantity'], false, $packages[$unitPrice['unit']] ?? []),
        ));
    }

    /**
     * Části textu dlaždice z řádků velkým písmem: značka (první řádek verzálkami menším písmem),
     * název (tučné řádky), upřesnění a balení (začátek řádku „130 g | 1 kg = …“).
     *
     * @param  Tile  $tile
     * @return array{brand: string|null, name: list<string>, details: list<string>, package: string|null}
     */
    private function parts(array $tile): array
    {
        $brand = null;
        $name = [];
        $details = [];
        $package = null;
        $leading = true;
        $nameHeight = $this->textHeight($tile['lines']) - self::NAME_HEIGHT_TOLERANCE;
        foreach ($tile['lines'] as $line) {
            // Menší písmo je cena za jednotku a výrobce
            if (! $this->isTextLine($line, $tile)) {
                continue;
            }

            $text = trim(explode(self::PART_SEPARATOR, $line->text())[0]);
            $last = array_key_last($name);
            // Značka verzálkami menším písmem na začátku, i na dvou řádcích („VELKO-“ / „POPOVICKÝ“, „DLE“ / „GUSTA“)
            $leading = $leading && $line->height() < $nameHeight && mb_strtoupper($text) === $text;
            if ($leading) {
                $brand = $brand === null ? $text : $this->joinLines($brand, $text);
            } elseif ($last !== null && $details === [] && preg_match(self::HYPHEN_PATTERN, $name[$last]) === 1) {
                // Název rozdělený na konci řádku pokračuje i menším písmem
                $name[$last] = $this->joinLines($name[$last], mb_strtolower($text));
            } elseif ($package === null && preg_match(self::PACKAGE_LINE_PATTERN, $text) === 1 && TileUnits::packages($text) !== []) {
                $package = $text;
            } elseif ($line->height() >= $nameHeight && $details === []) {
                $name[] = $this->titleCase($text);
            } else {
                $details[] = $text;
            }
        }

        return ['brand' => $brand, 'name' => $name, 'details' => $details, 'package' => $package];
    }

    /**
     * Dva řádky jednoho údaje: slovo rozdělené spojovníkem na konci řádku bez mezery („VELKO-“ +
     * „POPOVICKÝ“), jinak s mezerou.
     */
    private function joinLines(string $first, string $second): string
    {
        return preg_match(self::HYPHEN_PATTERN, $first) === 1 ? mb_substr($first, 0, -1).$second : $first.' '.$second;
    }

    /**
     * Řádek psaný celý verzálkami (celostátní leták „PÁRKY SE SÝREM“, značka „MADETA“) s velkým jen
     * prvním písmenem („Párky se sýrem“); jiný beze změny.
     */
    private function titleCase(string $text): string
    {
        if (preg_match(self::LETTERS_PATTERN, $text) !== 1 || mb_strtoupper($text) !== $text) {
            return $text;
        }
        $lower = mb_strtolower($text);

        return mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
    }

    /**
     * Nabídka z ověřené dlaždice. U ceny s aplikací je `price` cena bez aplikace a `loyalty_price`
     * cena s kupónem v aplikaci COOP.
     *
     * @param  Tile  $tile
     * @param  array{price: int, loyalty: int|null, package: string|null, regularIndex: int|null}  $verified
     * @param  array{CarbonImmutable, CarbonImmutable}  $validity
     */
    private function offer(array $tile, array $verified, array $validity, int $page, string $sourceUrl): OfferData
    {
        $parts = $this->parts($tile);
        $brand = $parts['brand'] === null ? null : Text::clean($this->titleCase($parts['brand']));
        $name = Text::join($brand, implode(' ', $parts['name'])) ?? '';
        $packageText = $parts['package'] ?? $verified['package'];
        // Řádky upřesnění jsou jeden text zalomený do sloupce („vybrané“ / „druhy“), čárky v něm zůstanou
        $description = Text::clean(rtrim(implode(' ', $parts['details']), ', '));

        return new OfferData(
            externalId: $this->ids->for($name, $packageText, $verified['price']),
            name: $name,
            offerType: OfferType::PromoPrice,
            validFrom: $validity[0],
            validTo: $validity[1],
            raw: [
                'page' => $page,
                'lines' => array_map(fn (PdfLine $line): string => $line->text(), $tile['lines']),
                'price' => $verified['price'],
                'loyaltyPrice' => $verified['loyalty'],
            ],
            price: $verified['price'],
            loyaltyPrice: $verified['loyalty'],
            loyaltyProgram: $verified['loyalty'] === null ? null : LoyaltyProgram::CoopCesko,
            brand: $brand,
            description: $description,
            variantNote: $this->variants->detect($name, $description),
            packageText: $packageText,
            package: $this->packages->parse($packageText),
            sourceUrl: $sourceUrl,
        );
    }
}
