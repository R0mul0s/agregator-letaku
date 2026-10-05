<?php

/**
 * Údaje provozovatele z config/letaky.php (R51) — patička webu a e-mailů, právní texty.
 * Adresa sídla je seznam řádků: patička je vypíše pod sebou, v textu se spojí čárkou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Support;

final class Operator
{
    /** Oddělovač řádků adresy v souvislém textu („Rodov 133, 503 03 Smiřice“). */
    private const ADDRESS_SEPARATOR = ', ';

    /**
     * Řádky adresy sídla (prázdné vynechá); prázdný seznam = adresa nevyplněná.
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        $lines = config('letaky.operator.address');

        return is_array($lines)
            ? array_values(array_filter(array_map(fn (mixed $line): string => trim((string) $line), $lines), fn (string $line): bool => $line !== ''))
            : [];
    }

    /**
     * Telefon pro odkaz tel: — bez mezer („+420736449607“), null = nevyplněný.
     */
    public function phoneHref(): ?string
    {
        $phone = preg_replace('/[^\d+]/', '', (string) config('letaky.operator.phone'));

        return $phone === '' || $phone === null ? null : $phone;
    }

    /**
     * Adresa sídla na jednom řádku, null = nevyplněná.
     */
    public function address(): ?string
    {
        $lines = $this->addressLines();

        return $lines === [] ? null : implode(self::ADDRESS_SEPARATOR, $lines);
    }
}
