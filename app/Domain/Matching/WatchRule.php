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
        return new self(
            self::terms($item->keywords, $normalizer),
            self::terms($item->variant_keywords, $normalizer),
            array_merge(...self::terms($item->exclude_keywords, $normalizer) ?: [[]]),
        );
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
