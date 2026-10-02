<?php

/**
 * Množství v balení z textu obchodu („4x0,33 l plech“, „20 kusů“, „cena za 1 kg“, „Mléko 1,5% 1l“).
 *
 * Nejednoznačné údaje („250 ml/500 ml“, „5 g-30 g“) vrací null — cena za jednotku
 * by z nich byla zavádějící.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Parsing;

use App\Domain\Offers\Data\PackageSize;
use App\Enums\PackageUnit;

final class PackageParser
{
    /**
     * Volitelný násobek („4x“, „3 ×“), množství s desetinnou čárkou a jednotka, za kterou
     * nenásleduje písmeno („l elopak“ ano, „lahev“ ne). Delší jednotky dřív než kratší.
     */
    private const SIZE_PATTERN = '/(?:(\d+)\s*[x×]\s*)?(\d+(?:[.,]\d+)?)\s*(kusů|kusy|kus|kg|ks|ml|g|l)(?!\p{L})/iu';

    /** Údaje, ve kterých obchod uvádí víc variant balení nebo rozsah („5 g-30 g“, „5-30 g“). */
    private const AMBIGUOUS_PATTERN = '/\/|\d\s*\p{L}*\s*-\s*\d/u';

    /** Úvod údaje o ceně za jednotku u zboží na váhu („cena za 1 kg“). */
    private const PRICE_PER_PREFIX = '/^cena\s+za\s+/iu';

    /**
     * Jednotka textu => [jednotka balení, násobek na základní jednotku].
     *
     * @var array<string, array{PackageUnit, int}>
     */
    private const UNITS = [
        'g' => [PackageUnit::Gram, 1],
        'kg' => [PackageUnit::Gram, 1000],
        'ml' => [PackageUnit::Milliliter, 1],
        'l' => [PackageUnit::Milliliter, 1000],
        'ks' => [PackageUnit::Piece, 1],
        'kus' => [PackageUnit::Piece, 1],
        'kusy' => [PackageUnit::Piece, 1],
        'kusů' => [PackageUnit::Piece, 1],
    ];

    /**
     * Balení z údaje, který obsahuje jen balení (Kaufland `unit`): množství musí být na začátku.
     */
    public function parse(?string $text): ?PackageSize
    {
        $text = trim(preg_replace(self::PRICE_PER_PREFIX, '', trim((string) $text)) ?? '');

        if ($text === '' || preg_match(self::AMBIGUOUS_PATTERN, $text) === 1) {
            return null;
        }

        if (preg_match(self::SIZE_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE) !== 1 || $matches[0][1] !== 0) {
            return null;
        }

        return $this->toSize($matches[1][0], $matches[2][0], $matches[3][0]);
    }

    /**
     * Balení uvedené v názvu produktu (Tesco `title`) — bere poslední výskyt, balení bývá na konci.
     */
    public function findInText(string $text): ?PackageSize
    {
        preg_match_all(self::SIZE_PATTERN, $text, $matches, PREG_SET_ORDER);
        $last = array_pop($matches);

        return $last === null ? null : $this->toSize($last[1], $last[2], $last[3]);
    }

    /**
     * Jednotka ceny za jednotku („kg“, „l“, „kus“) jako balení o jedné jednotce — zboží na váhu.
     */
    public function fromUnitLabel(string $label): ?PackageSize
    {
        $unit = self::UNITS[mb_strtolower(trim($label))] ?? null;

        return $unit === null ? null : new PackageSize($unit[1], $unit[0]);
    }

    /**
     * Složí balení z částí regulárního výrazu.
     */
    private function toSize(string $multiplier, string $amount, string $unitText): ?PackageSize
    {
        $unit = self::UNITS[mb_strtolower($unitText)] ?? null;
        $quantity = (float) str_replace(',', '.', $amount) * ($multiplier === '' ? 1 : (int) $multiplier);

        if ($unit === null || $quantity <= 0) {
            return null;
        }

        return new PackageSize($quantity * $unit[1], $unit[0]);
    }
}
