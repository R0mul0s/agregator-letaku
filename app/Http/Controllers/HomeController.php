<?php

/**
 * Úvodní stránka přihlášeného uživatele — slevy k jeho hlídaným položkám (R18, R19).
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
    public function __invoke(Request $request, MyOffers $myOffers, OfferPresenter $presenter, MentionPresenter $mentionPresenter): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Home', [
            'hasFollowedChains' => $user->followedChains()->exists(),
            'urls' => [
                'preferences' => route('preferences', absolute: false),
                'watchItems' => route('watch-items.index', absolute: false),
            ],
            'watchItems' => array_map(fn (array $group): array => [
                'id' => $group['watchItem']->id,
                'name' => $group['watchItem']->name,
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
