<?php

/**
 * Obchodní řetězce, jejichž letáky aplikace sleduje.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum Chain: string
{
    case Kaufland = 'kaufland';
    case Tesco = 'tesco';
    case Albert = 'albert';
    case Lidl = 'lidl';
    case Penny = 'penny';
    case Globus = 'globus';
    case Billa = 'billa';
    case Hruska = 'hruska';
    case Norma = 'norma';

    /**
     * Název obchodu pro zobrazení (lang/cs/app.php, skupina chains).
     */
    public function label(): string
    {
        return __('app.chains.'.$this->value);
    }

    /**
     * Název obchodu ve 2. pádě („akce Kauflandu“, lang/cs/app.php, skupina chains_genitive).
     */
    public function genitive(): string
    {
        return __('app.chains_genitive.'.$this->value);
    }

    /**
     * Obchody vypnuté v konfiguraci (`letaky.disabled_chains`, R155); neznámé hodnoty vynechá.
     *
     * @return list<self>
     */
    public static function disabled(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?self => is_string($value) ? self::tryFrom(mb_strtolower($value)) : null,
            config()->array('letaky.disabled_chains'),
        )));
    }

    /**
     * Obchody bez vypnutých (R155), v pořadí výčtu — nabídka obchodů ve filtrech a nastavení.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(self::cases(), fn (self $chain): bool => ! $chain->isDisabled()));
    }

    /**
     * Hodnoty vypnutých obchodů pro dotaz (`whereNotIn`).
     *
     * @return list<string>
     */
    public static function disabledValues(): array
    {
        return array_map(fn (self $chain): string => $chain->value, self::disabled());
    }

    /**
     * Je obchod vypnutý (R155)? Nestahuje se a jeho akce se nikde neukazují.
     */
    public function isDisabled(): bool
    {
        return in_array($this, self::disabled(), true);
    }
}
