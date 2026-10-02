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
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Zobrazí slevy po hlídaných položkách, s cenou, kterou uživatel zaplatí, a zmínky
     * v letácích bez ceny (R27).
     */
    public function __invoke(Request $request, MyOffers $myOffers, OfferPresenter $presenter, MentionPresenter $mentionPresenter, LandingController $landing): Response
    {
        // Nepřihlášený má na stejné adrese úvodní stránku (R44)
        $user = $request->user();
        if (! $user instanceof User) {
            return $landing->show();
        }

        return Inertia::render('Home', [
            'hasFollowedChains' => $user->followedChains()->exists(),
            'urls' => [
                'preferences' => route('preferences', absolute: false),
                'watchItems' => route('watch-items.index', absolute: false),
                'offersPreferences' => route('account', absolute: false).'#moje-slevy',
            ],
            // Předvolby řazení a minimální slevy (R41) — stránka je ukazuje u souhrnu
            'offersPreferences' => [
                'sortLabel' => $user->offers_sort->label(),
                'minDiscountPercent' => $user->min_discount_percent,
            ],
            'watchItems' => array_map(fn (array $group): array => [
                'id' => $group['watchItem']->id,
                'name' => $group['watchItem']->name,
                // Hlavička sbalené skupiny: nejnižší cena a akce upravit / přestat hlídat
                'lowestPrice' => $myOffers->lowestPrice($user, array_column($group['offers'], 'offer')),
                'fromCatalog' => $group['watchItem']->product_id !== null,
                'editUrl' => route('watch-items.index', [WatchItemController::EDIT_PARAMETER => $group['watchItem']->id], absolute: false),
                'deleteUrl' => route('watch-items.destroy', $group['watchItem'], absolute: false),
                'offers' => array_map(fn (array $match): array => [
                    ...$presenter->toPage($match['offer']),
                    'matchStatus' => $match['status']->value,
                ], $group['offers']),
                'mentions' => array_map(
                    fn (array $mention): array => $mentionPresenter->toPage($mention['page'], $mention['status']),
                    $group['mentions'],
                ),
            ], $myOffers->forUser($user)),
        ]);
    }
}
