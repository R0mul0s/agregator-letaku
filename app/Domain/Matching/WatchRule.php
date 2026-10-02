<?php

/**
 * Pravidla hlídané položky rozložená na slova (R18).
 *
 * Zápis: slova oddělená mezerou, alternativy jednoho slova svislítkem —
 * „mléko polotučné|1,5“ = „mléko“ a zároveň („polotučné“ nebo „1,5“).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Models\Product;
use App\Models\WatchItem;

final readonly class WatchRule
{
    private const ALTERNATIVE_SEPARATOR = '|';

    /**
     * @param  list<list<string>>  $keywords  Všechna slova musí být v nabídce (každé aspoň jednou z alternativ)
     * @param  list<list<string>>  $variant  Varianta — když chybí u souhrnné nabídky, shoda je „možná“
     * @param  list<string>  $exclude  Kterékoli z těchto slov nabídku vyřadí
     */
    public function __construct(
        public array $keywords,
        public array $variant,
        public array $exclude,
    ) {}

    /**
     * Pravidla z hlídané položky.
     */
    public static function fromWatchItem(WatchItem $item, TextNormalizer $normalizer): self
    {
        return self::fromText($item->keywords, $item->variant_keywords, $item->exclude_keywords, $normalizer);
    }

    /**
     * Pravidla produktu katalogu (R29) — stejný zápis jako u hlídané položky.
     */
    public static function fromProduct(Product $product, TextNormalizer $normalizer): self
    {
        return self::fromText($product->keywords, $product->variant_keywords, $product->exclude_keywords, $normalizer);
    }

    /**
     * Pravidla ze zápisu slov, varianty a vyloučení.
     */
    public static function fromText(?string $keywords, ?string $variant, ?string $exclude, TextNormalizer $normalizer): self
    {
        return new self(
            self::terms($keywords, $normalizer),
            self::terms($variant, $normalizer),
            array_merge(...self::terms($exclude, $normalizer) ?: [[]]),
        );
    }

    /**
     * Alternativy prvního slova — podle nich databáze předvybírá kandidáty.
     *
     * @return list<string>
     */
    public function firstWord(): array
    {
        return $this->keywords[0] ?? [];
    }

    /**
     * Slova zápisu, každé jako seznam normalizovaných alternativ; prázdná se vynechají.
     *
     * @return list<list<string>>
     */
    private static function terms(?string $text, TextNormalizer $normalizer): array
    {
        $terms = [];
        foreach (preg_split('/\s+/u', trim((string) $text), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $alternatives = array_values(array_filter(
                array_map($normalizer->word(...), explode(self::ALTERNATIVE_SEPARATOR, $term)),
                fn (string $alternative): bool => $alternative !== '',
            ));

            if ($alternatives !== []) {
                $terms[] = $alternatives;
            }
        }

        return $terms;
    }
}
