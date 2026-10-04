<?php

/**
 * Úvodní stránka přihlášeného uživatele — slevy k jeho hlídaným položkám (R18, R19).
 * Nepřihlášený má na stejné adrese úvodní stránku Slevohlídky (LandingController, R44).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Matching\MyOffers;
use App\Domain\Offers\MentionPresenter;
use App\Domain\Offers\OfferPresenter;
use App\Enums\DigestFrequency;
use App\Models\User;
use App\Support\CzechVocative;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Zobrazí slevy po hlídaných položkách, s cenou, kterou uživatel zaplatí, a zmínky
     * v letácích bez ceny (R27).
     */
    public function __invoke(Request $request, MyOffers $myOffers, OfferPresenter $presenter, MentionPresenter $mentionPresenter, LandingController $landing, CzechVocative $vocative): Response
    {
        // Nepřihlášený má na stejné adrese úvodní stránku (R44)
        $user = $request->user();
        if (! $user instanceof User) {
            return $landing->show();
        }

        // Vybrané prodejny (R49) — u akce, která neplatí všude, se vypíšou ty, kde platí
        $storeCodes = $user->selectedStoreCodes();

        return Inertia::render('Home', [
            'hasFollowedChains' => $user->followedChains()->exists(),
            // Pozdrav „Ahoj, Romane!“ — křestní jméno v 5. pádě (R47)
            'greetingName' => $vocative->firstName($user->name),
            'urls' => [
                'preferences' => route('preferences', absolute: false),
                'watchItems' => route('watch-items.index', absolute: false),
                'offersPreferences' => route('account', absolute: false).'#moje-slevy',
                'digest' => route('account', absolute: false).'#souhrn',
            ],
            // E-mailový souhrn (R42) — prázdná skupina na něj upozorní; null = vypnutý
            'digestFrequency' => $user->digest_frequency === DigestFrequency::Off ? null : mb_strtolower($user->digest_frequency->label()),
            // Předvolby řazení a minimální slevy (R41) — stránka je ukazuje u souhrnu
            'offersPreferences' => [
                'sortLabel' => $user->offers_sort->label(),
                'minDiscountPercent' => $user->min_discount_percent,
            ],
            'watchItems' => array_map(fn (array $group): array => [
                'id' => $group['watchItem']->id,
                'name' => $group['watchItem']->name,
                'fromCatalog' => $group['watchItem']->product_id !== null,
                'editUrl' => route('watch-items.index', [WatchItemController::EDIT_PARAMETER => $group['watchItem']->id], absolute: false),
                'deleteUrl' => route('watch-items.destroy', $group['watchItem'], absolute: false),
                'offers' => array_map(fn (array $match): array => [
                    ...$presenter->toPage($match['offer'], $storeCodes),
                    'matchStatus' => $match['status']->value,
                    // Cena, kterou uživatel zaplatí (s kartou, pokud ji má) — nejnižší cena
                    // v hlavičce skupiny se počítá z akcí na stránce, i po výběru obchodu (R55)
                    'userPrice' => $myOffers->userPrice($user, $match['offer']),
                ], $group['offers']),
                'mentions' => array_map(
                    fn (array $mention): array => $mentionPresenter->toPage($mention['page'], $mention['status']),
                    $group['mentions'],
                ),
            ], $myOffers->forUser($user)),
        ]);
    }
}
