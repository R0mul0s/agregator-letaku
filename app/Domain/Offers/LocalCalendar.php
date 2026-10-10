<?php

/**
 * Místní kalendářní data (R7). Akce platí po dnech v Europe/Prague, aplikace běží v UTC.
 *
 * Datum se drží jako CarbonImmutable o půlnoci UTC s místním datem — stejně jako ho
 * vrací přetypování `immutable_date` z databáze.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class LocalCalendar
{
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * Dnešní místní datum.
     */
    public function today(): CarbonImmutable
    {
        return $this->date(CarbonImmutable::now($this->timezone())->format(self::DATE_FORMAT));
    }

    /**
     * Místní datum z textu „2026-09-30“.
     */
    public function date(string $localDate): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $localDate, 'UTC')
            ?: throw new InvalidArgumentException("Neplatné datum „{$localDate}“.");
    }

    /**
     * Místní datum začátku platnosti z okamžiku v UTC („2026-09-29T22:00:00Z“ = 30. 9.).
     */
    public function startFromInstant(string $instant): CarbonImmutable
    {
        return $this->date(CarbonImmutable::parse($instant)->setTimezone($this->timezone())->format(self::DATE_FORMAT));
    }

    /**
     * Poslední místní den platnosti z okamžiku konce v UTC. Obchody uvádějí konec jako
     * půlnoc následujícího dne („2026-10-04T22:00:00Z“ = do 4. 10.) i jako poslední
     * sekundu dne („21:59:59Z“) — o sekundu dřív je v obou případech poslední den.
     */
    public function endFromInstant(string $instant): CarbonImmutable
    {
        return $this->date(CarbonImmutable::parse($instant)->subSecond()->setTimezone($this->timezone())->format(self::DATE_FORMAT));
    }

    /**
     * Okamžik začátku místního dne v UTC (2026-10-05 = „2026-10-04 22:00:00“ UTC) — pro
     * porovnání se sloupci času (`created_at`, `withdrawn_at`), které databáze drží v UTC.
     */
    public function startOfDayInstant(CarbonImmutable $localDate): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $localDate->format(self::DATE_FORMAT), $this->timezone())?->utc()
            ?? throw new InvalidArgumentException("Neplatné datum „{$localDate->format(self::DATE_FORMAT)}“.");
    }

    /**
     * Místní datum okamžiku v UTC (čas z databáze).
     */
    public function dateOfInstant(CarbonImmutable $instant): CarbonImmutable
    {
        return $this->date($instant->setTimezone($this->timezone())->format(self::DATE_FORMAT));
    }

    /**
     * Zobrazovací časová zóna z konfigurace.
     */
    private function timezone(): string
    {
        return config()->string('letaky.display_timezone');
    }
}
