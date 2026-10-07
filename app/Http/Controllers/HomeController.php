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
use App\Domain\Matching\WaitAdvice;
use App\Domain\Offers\MentionPresenter;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\PriceHistory;
use App\Enums\DigestFrequency;
use App\Enums\OffersSort;
use App\Models\User;
use App\Support\CzechVocative;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Zobrazí slevy po hlídaných položkách, s cenou, kterou uživatel zaplatí, a zmínky
     * v letácích bez ceny (R27); akce, které ještě nezačaly, zvlášť (R76).
     */
    public function __invoke(Request $request, MyOffers $myOffers, OfferPresenter $presenter, MentionPresenter $mentionPresenter, LandingController $landing, CzechVocative $vocative, PriceHistory $priceHistory, WaitAdvice $waitAdvice): Response
    {
        // Nepřihlášený má na stejné adrese úvodní stránku (R44)
        $user = $request->user();
        if (! $user instanceof User) {
            return $landing->show();
        }

        // Vybrané prodejny (R49) — u akce, která neplatí všude, se vypíšou ty, kde platí
        $storeCodes = $user->selectedStoreCodes();
        $groups = $myOffers->forUser($user);
        // „Je to opravdu sleva?“ (R59) — jedním dotazem pro akce všech skupin, i budoucí (R76)
        $history = $priceHistory->forOffers(array_merge(...array_map(
            fn (array $group): array => array_column([...$group['offers'], ...$group['upcoming']], 'offer'),
            $groups,
        )));
        $offerToPage = fn (array $match): array => [
            ...$presenter->toPage($match['offer'], $storeCodes, $history[$match['offer']->id] ?? null),
            'matchStatus' => $match['status']->value,
            // Cena, kterou uživatel zaplatí (s kartou, pokud ji má) — nejnižší cena
            // v hlavičce skupiny se počítá z akcí na stránce, i po výběru obchodu (R55)
            'userPrice' => $myOffers->userPrice($user, $match['offer']),
        ];

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
            // Předvolby řazení a minimální slevy (R41) — řazení jde změnit přímo na stránce (R100),
            // uloží se hned do účtu (stejný požadavek jako Můj účet, s celým stavem)
            'offersPreferences' => [
                'sort' => $user->offers_sort->value,
                'sortOptions' => array_map(
                    fn (OffersSort $sort): array => ['value' => $sort->value, 'label' => $sort->label()],
                    OffersSort::cases(),
                ),
                'minDiscountPercent' => $user->min_discount_percent,
                'updateUrl' => route('account.offers-preferences', absolute: false),
            ],
            'watchItems' => array_map(fn (array $group): array => [
                'id' => $group['watchItem']->id,
                'name' => $group['watchItem']->name,
                'fromCatalog' => $group['watchItem']->product_id !== null,
                'editUrl' => route('watch-items.index', [WatchItemController::EDIT_PARAMETER => $group['watchItem']->id], absolute: false),
                'deleteUrl' => route('watch-items.destroy', $group['watchItem'], absolute: false),
                'offers' => array_map($offerToPage, $group['offers']),
                // Akce, které ještě nezačaly — sekce „Brzy“ (R76)
                'upcoming' => array_map($offerToPage, $group['upcoming']),
                // „Vyplatí se počkat“ (R76): budoucí akce výrazně levnější než dnešní
                'waitTip' => $waitAdvice->for($user, array_column($group['offers'], 'offer'), array_column($group['upcoming'], 'offer')),
                'mentions' => array_map(
                    fn (array $mention): array => $mentionPresenter->toPage($mention['page'], $mention['status']),
                    $group['mentions'],
                ),
            ], $groups),
        ]);
    }
}
