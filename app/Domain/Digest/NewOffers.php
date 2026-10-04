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
     * Akce z Mých slev po hlídaných položkách, které obchod nabídl po `$since`; null = všechny
     * aktuální. Položky bez nové akce vynechá.
     *
     * @return list<array{watchItem: WatchItem, offers: list<Offer>}>
     */
    public function forUser(User $user, ?CarbonImmutable $since): array
    {
        $groups = [];
        foreach ($this->myOffers->forUser($user, withMentions: false) as $group) {
            $new = array_values(array_filter(
                array_column($group['offers'], 'offer'),
                fn (Offer $offer): bool => $since === null || $offer->created_at > $since,
            ));
            if ($new !== []) {
                $groups[] = ['watchItem' => $group['watchItem'], 'offers' => $new];
            }
        }

        return $groups;
    }
}
