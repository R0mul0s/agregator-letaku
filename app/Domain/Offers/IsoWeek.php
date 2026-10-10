<?php

/**
 * Kalendářní týden podle ISO 8601 (pondělí–neděle, týden 1 obsahuje první čtvrtek roku) pro
 * stránku Nejlepší slevy týdne (R128). V adrese jako „2026-41“ (rok ISO týdne, ne kalendářní
 * — 29. 12. 2025 patří do týdne 2026-01).
 *
 * Dny se drží jako CarbonImmutable o půlnoci UTC s místním datem jako v LocalCalendar.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class IsoWeek
{
    /** Týden v adrese: rok a dvoumístné číslo týdne („2026-41“, „2027-01“). */
    private const SLUG_PATTERN = '/^(\d{4})-(\d{2})$/';

    /** Vzor části adresy pro routu (bez oddělovačů, SLUG_PATTERN je přísnější). */
    public const ROUTE_PATTERN = '\d{4}-\d{2}';

    /** 28. prosinec leží vždy v posledním ISO týdnu roku. */
    private const LAST_WEEK_DAY = 28;

    private const DECEMBER = 12;

    private const DAYS_IN_WEEK = 7;

    /** Den a měsíc s nezlomitelnou mezerou („5. 10.“), jako ShortDate. */
    private const DAY_MONTH = 'j.'.PriceFormatter::NO_BREAK_SPACE.'n.';

    private function __construct(
        public int $year,
        public int $number,
    ) {}

    /**
     * Týden, do kterého patří místní datum.
     */
    public static function containing(CarbonImmutable $localDate): self
    {
        // Formát „o“ = rok ISO týdne, „W“ = číslo týdne (isoWeek() je zároveň setter, vrací int|static)
        return new self((int) $localDate->format('o'), (int) $localDate->format('W'));
    }

    /**
     * Týden z části adresy („2026-41“), nebo null — jiný tvar nebo týden, který rok nemá.
     */
    public static function fromSlug(string $slug): ?self
    {
        if (preg_match(self::SLUG_PATTERN, $slug, $match) !== 1) {
            return null;
        }

        $year = (int) $match[1];
        $number = (int) $match[2];
        $weeksInYear = CarbonImmutable::create($year, self::DECEMBER, self::LAST_WEEK_DAY, 0, 0, 0, 'UTC')?->isoWeek() ?? 0;

        return $number >= 1 && $number <= $weeksInYear ? new self($year, $number) : null;
    }

    /**
     * Část adresy „2026-41“.
     */
    public function slug(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->number);
    }

    /**
     * Pondělí týdne (místní datum).
     */
    public function monday(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, 1, 1, 0, 0, 0, 'UTC')
            ?->setISODate($this->year, $this->number)
            ?? throw new LogicException('Neplatný týden.');
    }

    /**
     * Neděle týdne (místní datum).
     */
    public function sunday(): CarbonImmutable
    {
        return $this->monday()->addDays(self::DAYS_IN_WEEK - 1);
    }

    /**
     * Předchozí týden.
     */
    public function previous(): self
    {
        return self::containing($this->monday()->subWeek());
    }

    /**
     * Následující týden.
     */
    public function next(): self
    {
        return self::containing($this->monday()->addWeek());
    }

    /**
     * Je to stejný týden?
     */
    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->number === $other->number;
    }

    /**
     * Je týden před jiným?
     */
    public function isBefore(self $other): bool
    {
        return [$this->year, $this->number] < [$other->year, $other->number];
    }

    /**
     * Rozsah dnů po česku: „5.–11. 10. 2026“, přes konec měsíce „28. 9. – 4. 10. 2026“,
     * přes konec roku „29. 12. 2025 – 4. 1. 2026“. Mezery uvnitř data nezlomitelné.
     */
    public function range(): string
    {
        $monday = $this->monday();
        $sunday = $this->sunday();
        $space = PriceFormatter::NO_BREAK_SPACE;

        if ($monday->year !== $sunday->year) {
            return $monday->format(self::DAY_MONTH.$space.'Y').' – '.$sunday->format(self::DAY_MONTH.$space.'Y');
        }
        if ($monday->month !== $sunday->month) {
            return $monday->format(self::DAY_MONTH).' – '.$sunday->format(self::DAY_MONTH.$space.'Y');
        }

        return $monday->format('j.').'–'.$sunday->format(self::DAY_MONTH.$space.'Y');
    }
}
