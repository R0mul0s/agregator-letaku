<?php

/**
 * E-mailový souhrn nových akcí hlídaných položek (R42). Cron ho volá po ranním stažení akcí;
 * uživatel dostane souhrn podle své volby (denně / týdně), jen když od minulého souhrnu
 * přibyly akce. Akce jsou stejné jako v Mých slevách (NewOffers — sledované obchody, karty,
 * minimální sleva), „nová“ = obchod ji poprvé nabídl po posledním souhrnu (`created_at`).
 *
 * Po dávkách (R54): jedno volání zpracuje nejvýš `letaky.digest.users_per_run` uživatelů,
 * od nejdéle čekajících — vejde se do limitu požadavku na hostingu i do limitu 300 e-mailů
 * za hodinu. Kdo nové akce nemá, je zpracovaný taky (`digest_sent_at`), jinak by dávku
 * zabíral při každém volání a na další by nedošlo. Cron se proto volá několikrát dopoledne.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Digest\Actions;

use App\Domain\Digest\NewOffers;
use App\Domain\Matching\MyOffers;
use App\Domain\Offers\LocalCalendar;
use App\Enums\DigestFrequency;
use App\Mail\DigestMail;
use App\Models\Offer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendDigests
{
    public function __construct(
        private readonly MyOffers $myOffers,
        private readonly NewOffers $newOffers,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Pošle souhrny jedné dávce uživatelů, kterým je čas; vrátí počet odeslaných. Chyba
     * u jednoho uživatele (např. odmítnutá adresa) se zapíše do logu, uživatel zůstane
     * nezpracovaný na další volání a ostatní dostanou souhrn dál.
     */
    public function __invoke(): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        // Bez stažení akcí od posledního souhrnu nové akce být nemůžou (R58) — okamžité
        // upozornění by jinak každou hodinu znovu počítalo Moje slevy všem
        $lastImport = $this->newOffers->lastImportFinishedAt();
        if ($lastImport === null) {
            return 0;
        }

        // Jen na ověřenou adresu (R51) — jinak by šlo souhrny posílat na cizí e-mail.
        // Nejdřív kdo souhrn ještě nedostal (null je v MariaDB při řazení vzestupně první).
        $users = User::query()
            ->whereNotNull('email_verified_at')
            ->where(fn (Builder $query) => $this->whereDue($query, $now))
            ->where(fn (Builder $query) => $query->whereNull('digest_sent_at')->orWhere('digest_sent_at', '<', $lastImport))
            ->orderBy('digest_sent_at')
            ->orderBy('id')
            ->limit(config()->integer('letaky.digest.users_per_run'))
            ->get();

        foreach ($users as $user) {
            try {
                if ($this->sendTo($user)) {
                    $sent++;
                }
                $user->forceFill(['digest_sent_at' => $now])->save();
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $sent;
    }

    /**
     * Uživatelé se zapnutým souhrnem, kterým od posledního uplynul interval zvolené četnosti.
     * První souhrn je hned.
     *
     * @param  Builder<User>  $query
     */
    private function whereDue(Builder $query, CarbonImmutable $now): void
    {
        foreach (DigestFrequency::cases() as $frequency) {
            $hours = $frequency->intervalHours();
            if ($hours === null) {
                continue;
            }

            $query->orWhere(fn (Builder $query) => $query
                ->where('digest_frequency', $frequency->value)
                ->where(fn (Builder $query) => $query
                    ->whereNull('digest_sent_at')
                    ->orWhere('digest_sent_at', '<=', $now->subHours($hours))));
        }
    }

    /**
     * Pošle uživateli souhrn, pokud má nové akce.
     */
    private function sendTo(User $user): bool
    {
        $today = $this->calendar->today();
        $items = array_map(fn (array $group): array => [
            'name' => $group['watchItem']->name,
            'offers' => array_map(fn (Offer $offer): array => [
                'name' => $offer->name,
                'chain' => $offer->chain->label(),
                'price' => $this->myOffers->userPrice($user, $offer),
                'discountPercent' => $offer->effectiveDiscountPercent(),
                // Začátek jen u akce, která ještě nezačala (R76)
                'validFrom' => $offer->isUpcoming($today) ? $offer->valid_from : null,
                'validTo' => $offer->valid_to,
            ], $group['offers']),
        ], $this->newOffers->forUser($user, $user->digest_sent_at));

        if ($items === []) {
            return false;
        }

        Mail::to($user)->send(new DigestMail($user, $items));

        return true;
    }
}
