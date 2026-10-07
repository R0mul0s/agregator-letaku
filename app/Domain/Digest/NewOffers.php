<?php

/**
 * Nové akce na hlídané zboží od určitého okamžiku — společný základ e-mailového souhrnu (R42)
 * a upozornění v telefonu (R66). Akce jsou stejné jako v Mých slevách (MyOffers — sledované
 * obchody, karty, minimální sleva), „nová“ = obchod ji poprvé nabídl po daném čase (`created_at`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Digest;

use App\Domain\Matching\MyOffers;
use App\Enums\ScrapeStatus;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Models\WatchItem;
use Carbon\CarbonImmutable;

final class NewOffers
{
    public function __construct(private readonly MyOffers $myOffers) {}

    /**
     * Konec posledního stažení akcí (úspěšného nebo částečného) jako text z databáze; null = žádné.
     * Bez stažení od posledního upozornění nové akce být nemůžou (R58) — kdo ho nedostal,
     * nemusí se znovu počítat.
     */
    public function lastImportFinishedAt(): ?string
    {
        $finishedAt = ScrapeRun::query()
            ->whereIn('status', [ScrapeStatus::Succeeded, ScrapeStatus::Partial])
            ->max('finished_at');

        return is_string($finishedAt) ? $finishedAt : null;
    }

    /**
     * Okamžik, do kterého jsou nové akce v databázi úplné (R106): teď, nebo těsně před začátkem
     * nejstaršího stažení, které ještě běží. Import nastaví akci `created_at` při skládání řádku,
     * ale potvrdí ji až na konci transakce — kdyby si cron zapsal jako hranici `now()`, akce
     * potvrzené po něm by měly starší `created_at` a nikdy by nebyly „nové“. Stažení, které
     * běží déle než zámek, nedoběhlo (R57) a hranici nezdržuje. Akce stažení, které začalo až
     * po tomhle okamžiku, vznikají nejdřív po stažení zdroje (sekundy), takže jsou novější.
     */
    public function horizon(): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $running = ScrapeRun::query()
            ->where('status', ScrapeStatus::Running)
            ->where('started_at', '>=', $now->subSeconds(config()->integer('letaky.import.lock_seconds')))
            ->orderBy('started_at')
            ->first(['started_at']);

        // Řádky běžícího stažení mají `created_at` od jeho začátku (na sekundy) — hranice o sekundu dřív
        $beforeRunning = $running?->started_at->subSecond();

        return $beforeRunning !== null && $beforeRunning->lessThan($now) ? $beforeRunning : $now;
    }

    /**
     * Akce z Mých slev po hlídaných položkách, které obchod nabídl po `$since` a nejpozději
     * v `$until` (horizon); `$since` null = všechny aktuální. Patří sem i akce, které ještě
     * nezačaly (R76) — upozornění přijde hned, jak je obchod zveřejní, a akce v něm nese datum
     * začátku. Položky bez nové akce vynechá.
     *
     * @return list<array{watchItem: WatchItem, offers: list<Offer>}>
     */
    public function forUser(User $user, ?CarbonImmutable $since, CarbonImmutable $until): array
    {
        $groups = [];
        foreach ($this->myOffers->forUser($user, withMentions: false) as $group) {
            $new = array_values(array_filter(
                array_column([...$group['offers'], ...$group['upcoming']], 'offer'),
                fn (Offer $offer): bool => ($since === null || $offer->created_at > $since) && $offer->created_at <= $until,
            ));
            if ($new !== []) {
                $groups[] = ['watchItem' => $group['watchItem'], 'offers' => $new];
            }
        }

        return $groups;
    }
}
