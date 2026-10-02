<?php

/**
 * Převod cen od obchodů na haléře (R7) — jediné místo, kde se z textu nebo čísla dělá cena.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Parsing;

use App\Domain\Offers\Exceptions\InvalidPrice;

final class PriceParser
{
    private const HALERS_PER_CROWN = 100;

    /** Počet desetinných míst ceny v Kč. */
    private const DECIMALS = 2;

    /** Cena v Kč: „29,90“, „1 299,00“, „29.9“, „149“ — mezery jako oddělovač tisíců. */
    private const PRICE_PATTERN = '/^(\d+)(?:[.,](\d{1,2}))?$/';

    /** První částka v Kč v textu: „8,90 Kč Více než…“, „Ušetřete 1/3 99,00 Kč/kg“. */
    private const AMOUNT_IN_TEXT_PATTERN = '/(\d{1,3}(?:[ \x{00A0}\x{202F}]\d{3})+|\d+)(?:[.,](\d{1,2}))?\s*Kč/u';

    /**
     * Převede text ceny v Kč na haléře.
     *
     * @throws InvalidPrice
     */
    public function parse(string $text): int
    {
        $normalized = preg_replace('/[\s\x{00A0}\x{202F}]+|Kč/u', '', $text) ?? '';

        if (preg_match(self::PRICE_PATTERN, $normalized, $matches) !== 1) {
            throw InvalidPrice::fromText($text);
        }

        return $this->toHalers($matches[1], $matches[2] ?? '');
    }

    /**
     * Jako parse(), prázdná hodnota je null.
     *
     * @throws InvalidPrice
     */
    public function parseOptional(?string $text): ?int
    {
        return $text === null || trim($text) === '' ? null : $this->parse($text);
    }

    /**
     * Převede číslo z JSON (27.9) na haléře přes text — násobení floatu by zaokrouhlovalo špatně.
     *
     * @throws InvalidPrice
     */
    public function fromFloat(float $crowns): int
    {
        return $this->parse(number_format($crowns, self::DECIMALS, '.', ''));
    }

    /**
     * Najde v textu první částku v Kč; null, když tam žádná není.
     */
    public function findFirst(string $text): ?int
    {
        if (preg_match(self::AMOUNT_IN_TEXT_PATTERN, $text, $matches) !== 1) {
            return null;
        }

        $crowns = preg_replace('/\D/', '', $matches[1]) ?? '';

        return $this->toHalers($crowns, $matches[2] ?? '');
    }

    /**
     * Koruny a haléře (jedna nebo dvě číslice, „9“ = 90 h) na haléře.
     */
    private function toHalers(string $crowns, string $fraction): int
    {
        return (int) $crowns * self::HALERS_PER_CROWN + (int) str_pad($fraction, self::DECIMALS, '0');
    }
}
