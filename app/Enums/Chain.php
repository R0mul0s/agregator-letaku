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
    case Coop = 'coop';
    case Kosik = 'kosik';
    case Rohlik = 'rohlik';

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
     * Vypnuté obchody v pořadí výčtu (R155, R180): uvedené v `letaky.disabled_chains`
     * (`LETAKY_DISABLED_CHAINS`) a obchody vypnuté ve výchozím stavu (`sources.<obchod>.enabled_by_default`
     * false — Rohlík do písemného souhlasu), které nejsou v `letaky.enabled_chains` (`LETAKY_ENABLED_CHAINS`).
     * Vypnutí má přednost před zapnutím.
     *
     * @return list<self>
     */
    public static function disabled(): array
    {
        $disabled = self::fromConfigList('letaky.disabled_chains');
        $enabled = self::fromConfigList('letaky.enabled_chains');

        return array_values(array_filter(self::cases(), fn (self $chain): bool => in_array($chain, $disabled, true)
            || (config("letaky.sources.{$chain->value}.enabled_by_default") === false && ! in_array($chain, $enabled, true))));
    }

    /**
     * Obchody ze seznamu v konfiguraci (hodnoty z .env, na velikosti písmen nezáleží); neznámé vynechá.
     *
     * @return list<self>
     */
    private static function fromConfigList(string $key): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?self => is_string($value) ? self::tryFrom(mb_strtolower(trim($value))) : null,
            config()->array($key),
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
