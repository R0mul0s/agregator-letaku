<?php

/**
 * Časový rozpočet jednoho volání cronu (R106). Hosting požadavek po limitu ukončí bez varování
 * (O8) — dávka uživatelů proto skončí sama, dokud je čas, a zbytek zpracuje další volání.
 * Několik kroků v jednom požadavku si zbývající čas dělí rovným dílem, takže první krok
 * nevyčerpá čas ostatním; co krok nespotřebuje, zůstane dalším.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Support;

final class Deadline
{
    private function __construct(private readonly ?float $endsAt) {}

    /**
     * Bez omezení — příkaz artisan v konzoli, testy.
     */
    public static function none(): self
    {
        return new self(null);
    }

    /**
     * Rozpočet daný počtem sekund od teď.
     */
    public static function in(int $seconds): self
    {
        return new self(microtime(true) + $seconds);
    }

    /**
     * Vypršel už rozpočet?
     */
    public function passed(): bool
    {
        return $this->endsAt !== null && microtime(true) >= $this->endsAt;
    }

    /**
     * Rovný díl zbývajícího času pro jeden z `$parts` kroků, které ještě zbývají.
     */
    public function share(int $parts): self
    {
        if ($this->endsAt === null || $parts <= 1) {
            return $this;
        }

        $now = microtime(true);

        return new self($now + max(0.0, $this->endsAt - $now) / $parts);
    }
}
