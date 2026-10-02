<?php

/**
 * E-mailový souhrn nových akcí hlídaných položek (R42). Volá ho cron jednou denně po ranním
 * stažení akcí; uživatel dostane souhrn podle své volby (denně / týdně), jen když od minulého
 * souhrnu přibyly akce. Akce jsou stejné jako v Mých slevách (MyOffers — sledované obchody,
 * karty, minimální sleva), „nová“ = obchod ji poprvé nabídl po posledním souhrnu (`created_at`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Digest\Actions;

use App\Domain\Matching\MyOffers;
use App\Enums\DigestFrequency;
use App\Mail\DigestMail;
use App\Models\Offer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendDigests
{
    public function __construct(private readonly MyOffers $myOffers) {}

    /**
     * Pošle souhrny všem, kterým je čas; vrátí počet odeslaných. Chyba u jednoho uživatele
     * (např. odmítnutá adresa) se zapíše do logu a ostatní dostanou souhrn dál.
     */
    public function __invoke(): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        User::query()
            ->where('digest_frequency', '!=', DigestFrequency::Off->value)
            ->orderBy('id')
            ->each(function (User $user) use ($now, &$sent): void {
                try {
                    if ($this->isDue($user, $now) && $this->sendTo($user, $now)) {
                        $sent++;
                    }
                } catch (Throwable $error) {
                    report($error);
                }
            });

        return $sent;
    }

    /**
     * Uplynul od posledního souhrnu interval zvolené četnosti? První souhrn je hned.
     */
    private function isDue(User $user, CarbonImmutable $now): bool
    {
        $hours = $user->digest_frequency->intervalHours();

        return $hours !== null && ($user->digest_sent_at === null || $user->digest_sent_at->addHours($hours) <= $now);
    }

    /**
     * Pošle uživateli souhrn, pokud má nové akce; pak si zapamatuje čas odeslání.
     */
    private function sendTo(User $user, CarbonImmutable $now): bool
    {
        $items = [];
        foreach ($this->myOffers->forUser($user) as $group) {
            $new = array_values(array_filter(
                array_column($group['offers'], 'offer'),
                fn (Offer $offer): bool => $user->digest_sent_at === null || $offer->created_at > $user->digest_sent_at,
            ));
            if ($new !== []) {
                $items[] = [
                    'name' => $group['watchItem']->name,
                    'offers' => array_map(fn (Offer $offer): array => [
                        'name' => $offer->name,
                        'chain' => $offer->chain->label(),
                        'price' => $this->myOffers->userPrice($user, $offer),
                        'discountPercent' => $offer->effectiveDiscountPercent(),
                        'validTo' => $offer->valid_to,
                    ], $new),
                ];
            }
        }

        if ($items === []) {
            return false;
        }

        Mail::to($user)->send(new DigestMail($user, $items));
        $user->forceFill(['digest_sent_at' => $now])->save();

        return true;
    }
}
