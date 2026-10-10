<?php

/**
 * Odpověď hlídání pro monitoring (UptimeRobot): na každém řádku jedna kontrolovaná věc
 * („Tesco — OK, naposledy 2. 10. 11:00“), 200, když jsou všechny v pořádku, jinak 503.
 * Sdílí `/health/imports` a `/health/tasks` (R115). Veřejné — jen časy, žádný text chyby.
 * Výpadky zvlášť (outages) čte upozornění adminům (RecordHealthAlerts, R126).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

final class HealthReport
{
    /** Čas posledního běhu ve výpisu (místní čas). */
    private const TIME_FORMAT = 'j. n. H:i';

    /** @var list<string> */
    private array $lines = [];

    /**
     * Řádky výpadků podle názvu kontrolované věci — upozornění adminům (R126) porovnává názvy,
     * text se mění s časem.
     *
     * @var array<string, string>
     */
    private array $outages = [];

    private bool $healthy = true;

    /**
     * Přidá řádek: v pořádku, když poslední úspěch není starší než limit. Neúspěch po
     * posledním úspěchu se jen připíše — jedno selhání výpadek není, až to, které trvá.
     *
     * @param  string  $outageKey  Text výpadku v lang/cs/app.php (s :name a :at)
     */
    public function check(string $name, ?CarbonImmutable $succeededAt, int $maxAgeHours, string $outageKey, ?CarbonImmutable $failedAt = null): void
    {
        $ok = $succeededAt !== null && $succeededAt->greaterThan(CarbonImmutable::now()->subHours($maxAgeHours));
        $this->healthy = $this->healthy && $ok;

        $line = __($ok ? 'app.health.ok' : $outageKey, ['name' => $name, 'at' => $this->time($succeededAt)]);
        if ($failedAt !== null && ($succeededAt === null || $failedAt->greaterThan($succeededAt))) {
            $line = __('app.health.failed_after', ['line' => $line, 'at' => $this->time($failedAt)]);
        }

        $this->lines[] = $line;
        if (! $ok) {
            $this->outages[$name] = $line;
        }
    }

    /**
     * Výpadky: název kontrolované věci => řádek výpisu.
     *
     * @return array<string, string>
     */
    public function outages(): array
    {
        return $this->outages;
    }

    /**
     * Prostý text bez cache; 503, když je některý řádek výpadek.
     */
    public function response(): Response
    {
        return response(implode("\n", $this->lines)."\n", $this->healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Čas v místním čase, nebo „nikdy“.
     */
    private function time(?CarbonImmutable $at): string
    {
        return $at?->setTimezone(config()->string('letaky.display_timezone'))->format(self::TIME_FORMAT) ?? __('app.health.never');
    }
}
