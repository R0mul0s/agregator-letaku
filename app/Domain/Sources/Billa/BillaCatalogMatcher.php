<?php

/**
 * Párování dlaždice letáku Billy s produktem katalogu API (R89).
 *
 * PDF letáku nemá kód zboží. API ale vrací celý katalog včetně produktů, které dnes v akci nejsou,
 * a jejich běžnou cenu — ta je v letáku jako přeškrtnutá cena, „běžná cena“ u ceny s Klubem nebo
 * cena jednoho kusu u akce na množství. Produkt se proto hledá mezi produkty se **stejnou běžnou
 * cenou** a se **stejným balením**; z nich vyhrají ty, v jejichž názvu (a značce) je nejvíc slov
 * názvu z letáku (bez diakritiky, slovo může být začátkem slova katalogu: „Lipánek tvarohový“ ↔
 * „Madeta Lipánek Tvarohový vanilka 130g“) — aspoň polovina.
 *
 * Akční cena bez původní ceny („NAŠE CENA“) běžnou cenu v letáku nemá: produkt musí mít stejné
 * balení, **všechna** slova názvu a běžnou cenu vyšší než akční.
 *
 * Dlaždice s víc druhy („více druhů“, „2 druhy“) dostane všechny nejlépe sedící produkty — každý druh
 * je v API samostatný produkt a po začátku akce bude samostatnou akcí. Dlaždice bez druhů jen jediný
 * produkt (nebo víc produktů se stejným názvem — katalog má některé zboží dvakrát); víc různých
 * kandidátů = nejistota, dlaždice se nespáruje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Sources\Billa;

use App\Domain\Matching\TextNormalizer;
use App\Enums\OfferType;

final class BillaCatalogMatcher
{
    /** Porovnávají se slova aspoň o tolika znacích (bez „z“, „s“, „na“ a jednotek „g“, „ml“). */
    private const MIN_WORD_LENGTH = 3;

    /** Slova, která v letáku označují druh prodeje, ne výrobek („z naší pece“, „z teplého pultu“). */
    private const IGNORED_WORDS = ['nasi', 'pece', 'tepleho', 'pultu', 'lahudek'];

    /** Nejmenší podíl slov názvu z letáku, která musí být v názvu produktu (se stejnou běžnou cenou); polovina pouštěla „BILLA High Protein puding“ ↔ „BILLA Puding s vanilkovou příchutí“. */
    private const MIN_NAME_SCORE = 0.6;

    /**
     * Bez běžné ceny („NAŠE CENA“) musí sedět všechna slova a název z letáku musí mít aspoň dvě —
     * jedno slovo („Brusinky“) chytalo jiné výrobky se stejným balením.
     */
    private const FULL_NAME_SCORE = 1.0;

    private const PROMO_MIN_WORDS = 2;

    /** Procento v názvu („12%“, „31 %“). */
    private const PERCENT_IN_NAME_PATTERN = '/(\d+(?:,\d+)?)\s*%/u';

    /** Tolerance shody balení (podíl). */
    private const PACKAGE_TOLERANCE = 0.01;

    /** Víc velikostí balení „od 270 g“ — produkt katalogu může být i větší. */
    private const FROM_PACKAGE_PATTERN = '/\bod\s+\d/iu';

    /** @var array<string, list<string>> SKU => slova názvu produktu (počítají se jednou) */
    private array $productWords = [];

    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {}

    /**
     * Produkty katalogu podle běžné ceny — pro rychlé hledání kandidátů.
     *
     * @param  iterable<BillaCatalogProduct>  $products
     * @return array<int, list<BillaCatalogProduct>>
     */
    public function index(iterable $products): array
    {
        $index = [];
        foreach ($products as $product) {
            $index[$product->usualPrice][] = $product;
        }

        return $index;
    }

    /**
     * Produkty katalogu, na které se akce z dlaždice vztahuje; prázdné, když ji spárovat nejde
     * (žádný nebo víc různých kandidátů u dlaždice bez druhů).
     *
     * @param  array<int, list<BillaCatalogProduct>>  $index  Z `index()`
     * @return list<BillaCatalogProduct>
     */
    public function match(BillaLeafletItem $item, array $index): array
    {
        $words = $this->words($item->name);
        if ($words === []) {
            return [];
        }

        if ($item->usualPrice !== null) {
            $pool = $index[$item->usualPrice] ?? [];
            $minScore = self::MIN_NAME_SCORE;
        } elseif ($item->offerType === OfferType::PromoPrice && count($words) >= self::PROMO_MIN_WORDS) {
            $pool = array_merge(...array_values(array_filter($index, fn (int $usual): bool => $usual > $item->price, ARRAY_FILTER_USE_KEY)));
            $minScore = self::FULL_NAME_SCORE;
        } else {
            return [];
        }

        $best = 0.0;
        $candidates = [];
        foreach ($pool as $product) {
            // Bez běžné ceny musí balení sedět přesně — „od 39 g“ by pustilo cokoli většího
            if (! $this->packageMatches($item, $product, $item->usualPrice !== null)) {
                continue;
            }
            $score = $this->nameScore($words, $product);
            if ($score < $minScore || $score < $best || ! $this->percentsMatch($item->name, $product)
                || ($item->usualPrice === null && ! $this->brandMatches($words[0], $product))) {
                continue;
            }
            if ($score > $best) {
                $best = $score;
                $candidates = [];
            }
            $candidates[] = $product;
        }

        // Bez běžné ceny musí mít všechny druhy stejnou běžnou cenu — „Bohemia Sekt“ za 179,90 i 279,90
        // jsou dvě různé řady a akce platí nejspíš jen na jednu
        if ($item->usualPrice === null && count(array_unique(array_map(fn (BillaCatalogProduct $product): int => $product->usualPrice, $candidates))) > 1) {
            return [];
        }

        if ($item->variants || count($candidates) <= 1) {
            return $candidates;
        }

        // Bez druhů jen jeden výrobek — víc produktů se smí lišit jen kódem (katalog má zboží dvakrát)
        $names = array_unique(array_map(fn (BillaCatalogProduct $product): string => $this->normalizer->normalize($product->name), $candidates));

        return count($names) === 1 ? $candidates : [];
    }

    /**
     * Slova názvu bez diakritiky, bez čísel, jednotek a krátkých slov.
     *
     * @return list<string>
     */
    public function words(string $text): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', trim(str_replace(',', ' ', $this->normalizer->normalize($text)))),
            fn (string $word): bool => strlen($word) >= self::MIN_WORD_LENGTH && preg_match('/[a-z]/', $word) === 1
                && ! in_array($word, self::IGNORED_WORDS, true),
        )));
    }

    /**
     * Podíl slov názvu z letáku, která jsou v názvu (nebo značce) produktu — i jako začátek slova.
     *
     * @param  list<string>  $words
     */
    private function nameScore(array $words, BillaCatalogProduct $product): float
    {
        $productWords = $this->productWords[$product->sku] ??= $this->words(trim(($product->brand ?? '').' '.$product->name));

        $found = 0;
        foreach ($words as $word) {
            foreach ($productWords as $productWord) {
                if (str_starts_with($productWord, $word) || str_starts_with($word, $productWord)) {
                    $found++;

                    break;
                }
            }
        }

        return $found / count($words);
    }

    /**
     * Procenta v názvu z letáku („Zakysaná smetana 12%“, „Protein 30%“) musí být i v názvu produktu —
     * jinak by se spárovala smetana 15 %. „0%“ katalog často neuvádí („Můj skyr 0%“), proto se nekontroluje.
     */
    private function percentsMatch(string $leafletName, BillaCatalogProduct $product): bool
    {
        preg_match_all(self::PERCENT_IN_NAME_PATTERN, $leafletName, $matches);
        $productWords = explode(' ', trim($this->normalizer->normalize($product->name)));
        foreach ($matches[1] as $number) {
            if ((float) str_replace(',', '.', $number) > 0 && ! in_array($this->normalizer->word($number), $productWords, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Začíná název z letáku značkou produktu (nebo prvním slovem jeho názvu)? Bez běžné ceny je to
     * jediná kotva — „Zrnková káva 500 g“ by jinak chytila zrnkovou kávu všech značek.
     */
    private function brandMatches(string $firstWord, BillaCatalogProduct $product): bool
    {
        $nameWords = $this->words($product->name);
        foreach ([...$this->words((string) $product->brand), ...array_slice($nameWords, 0, 1)] as $word) {
            if (str_starts_with($word, $firstWord) || str_starts_with($firstWord, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sedí balení produktu na balení z letáku? U „od 270 g“ smí být produkt i větší (jen když to dovolí `$allowLarger`).
     */
    private function packageMatches(BillaLeafletItem $item, BillaCatalogProduct $product, bool $allowLarger): bool
    {
        if ($product->quantity === null || $product->unit === null) {
            return false;
        }

        $from = $allowLarger && preg_match(self::FROM_PACKAGE_PATTERN, (string) $item->packageText) === 1;
        foreach ($item->packages as $package) {
            if ($package['unit'] !== $product->unit) {
                continue;
            }
            if (abs($package['quantity'] - $product->quantity) <= $package['quantity'] * self::PACKAGE_TOLERANCE
                || ($from && $product->quantity >= $package['quantity'])) {
                return true;
            }
        }

        return false;
    }
}
