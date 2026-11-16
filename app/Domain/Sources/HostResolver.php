<?php

/**
 * Převod jména serveru na IP adresy pro kontrolu adres ke stažení (R186) — zdroj smí stahovat
 * jen z veřejných adres. Rozhraní kvůli testům: ty nesahají na síť ani na DNS (R11).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-16
 */

declare(strict_types=1);

namespace App\Domain\Sources;

interface HostResolver
{
    /**
     * IP adresy (IPv4 i IPv6), na které se jméno přeloží; prázdné, když se nepřeloží.
     *
     * @return list<string>
     */
    public function addresses(string $host): array;
}
