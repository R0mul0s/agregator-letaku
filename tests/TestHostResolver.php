<?php

/**
 * Podvržený převod jmen serverů pro testy (R186) — testy nesahají na DNS (R11). Každé jméno
 * „je“ na veřejné adrese, test může jménu nastavit jiné adresy (`$hosts`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-16
 */

declare(strict_types=1);

namespace Tests;

use App\Domain\Sources\HostResolver;

final class TestHostResolver implements HostResolver
{
    /** Veřejná adresa, na kterou se v testech přeloží každé jméno bez vlastního záznamu. */
    public const PUBLIC_ADDRESS = '93.184.216.34';

    /**
     * @param  array<string, list<string>>  $hosts  Jméno => adresy, na které se přeloží
     */
    public function __construct(public array $hosts = []) {}

    /**
     * Adresy jména z `$hosts`, jinak veřejná adresa.
     *
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        return $this->hosts[$host] ?? [self::PUBLIC_ADDRESS];
    }
}
