<?php

/**
 * Data z textu letáků („od 28. 12. do 3. 1. 2027“) — den a měsíc, rok často jen u konce
 * nebo vůbec. Leták přes Nový rok má začátek v předchozím roce (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Offers\Parsing;

use App\Domain\Offers\LocalCalendar;
use Carbon\CarbonImmutable;

final readonly class LeafletDates
{
    private const DATE_FORMAT = '%04d-%02d-%02d';

    /** Roky, mezi kterými se hledá datum bez roku nejbližší k referenčnímu dni. */
    private const NEAR_YEAR_OFFSETS = [-1, 0, 1];

    public function __construct(private LocalCalendar $calendar) {}

    /**
     * Místní datum, nebo null, když den v roce neexistuje (31. 4.).
     */
    public function date(int $day, int $month, int $year): ?CarbonImmutable
    {
        return checkdate($month, $day, $year) ? $this->calendar->date(sprintf(self::DATE_FORMAT, $year, $month, $day)) : null;
    }

    /**
     * Datum bez roku nejbližší k referenčnímu dni — „30. 12.“ k letáku do 5. 1. 2027 je 2026.
     */
    public function near(int $day, int $month, CarbonImmutable $reference): ?CarbonImmutable
    {
        $nearest = null;
        foreach (self::NEAR_YEAR_OFFSETS as $offset) {
            $date = $this->date($day, $month, $reference->year + $offset);
            if ($date !== null && ($nearest === null || $date->diffInDays($reference, true) < $nearest->diffInDays($reference, true))) {
                $nearest = $date;
            }
        }

        return $nearest;
    }

    /**
     * Platnost od–do. Chybí-li rok začátku, je to rok konce, nebo předchozí, když začátek
     * v roce leží za koncem (přes Nový rok); chybí-li rok konce, je to rok začátku, nebo
     * následující. Bez obou let rozhoduje konec nejbližší k referenčnímu dni. Null, když
     * datum neexistuje, rok nejde určit nebo je konec před začátkem.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function range(int $fromDay, int $fromMonth, ?int $fromYear, int $toDay, int $toMonth, ?int $toYear, ?CarbonImmutable $reference = null): ?array
    {
        $fromAfterTo = [$fromMonth, $fromDay] > [$toMonth, $toDay];
        if ($toYear === null && $fromYear === null) {
            $toYear = $reference === null ? null : $this->near($toDay, $toMonth, $reference)?->year;
        } elseif ($toYear === null) {
            $toYear = $fromAfterTo ? $fromYear + 1 : $fromYear;
        }
        if ($toYear === null) {
            return null;
        }

        $from = $this->date($fromDay, $fromMonth, $fromYear ?? ($fromAfterTo ? $toYear - 1 : $toYear));
        $to = $this->date($toDay, $toMonth, $toYear);

        return $from === null || $to === null || $from->greaterThan($to) ? null : [$from, $to];
    }
}
