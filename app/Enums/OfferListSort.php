<?php

/**
 * Řazení výpisu Všech akcí (R100) — hodnota je parametr adresy `?razeni=sleva`. Bez volby
 * záleží na situaci (defaultFor): s hledaným textem podle relevance, u produktu katalogu od
 * nejnižší ceny za jednotku („kde je nejlevněji“, R94), jinak doporučené.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Enums;

enum OfferListSort: string
{
    /** Skutečné slevy, nejdřív zveřejněné za poslední dny, od nejvyšší; pak ostatní akce od nejnovějších. */
    case Recommended = 'doporucene';

    /** Shoda s hledaným textem: název, značka, popis (R71) — jen s textem. */
    case Relevance = 'relevance';

    /** Od nejvyšší slevy v procentech; akce bez známé slevy na konec. */
    case Discount = 'sleva';

    /** Od nejnižší ceny za kilo, litr nebo kus; akce bez balení na konec. */
    case UnitPrice = 'cena';

    /** Od akce, která končí nejdřív. */
    case EndingSoon = 'konci';

    /**
     * Řazení, když uživatel žádné nezvolil: s textem relevance, u produktu cena za jednotku,
     * jinak doporučené.
     */
    public static function defaultFor(bool $hasText, bool $hasProduct): self
    {
        return match (true) {
            $hasText => self::Relevance,
            $hasProduct => self::UnitPrice,
            default => self::Recommended,
        };
    }

    /**
     * Řazení, které jde zvolit — relevance jen s hledaným textem.
     *
     * @return list<self>
     */
    public static function available(bool $hasText): array
    {
        return array_values(array_filter(self::cases(), fn (self $sort): bool => $hasText || $sort !== self::Relevance));
    }

    /**
     * Název řazení pro zobrazení (lang/cs/app.php, skupina ui.offer_list_sort).
     */
    public function label(): string
    {
        return __('app.ui.offer_list_sort.'.$this->value);
    }
}
