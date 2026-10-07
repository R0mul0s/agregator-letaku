<?php

/**
 * Úvodní stránka přihlášeného uživatele — slevy k jeho hlídaným položkám (R18, R19).
 * Nepřihlášený má na stejné adrese úvodní stránku Slevohlídky (LandingController, R44).
 * Řazení, štítky filtrů a dočasné přepnutí na všechny prodejny (R100, R101), pohled Podle
 * obchodů (R102).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Matching\ChainOverview;
use App\Domain\Matching\MyOffers;
use App\Domain\Matching\WaitAdvice;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\MentionPresenter;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\OfferSearch;
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
    /** Parametr adresy: `?prodejny=vse` = akce všech prodejen, ne jen vybraných (R101). */
    public const STORES_PARAMETER = 'prodejny';

    /** Hodnota parametru prodejen: všechny. */
    public const ALL_STORES = 'vse';

    /**
     * Zobrazí slevy po hlídaných položkách, s cenou, kterou uživatel zaplatí, a zmínky
     * v letácích bez ceny (R27); akce, které ještě nezačaly, zvlášť (R76). U akcí příznaky pro
     * štítky Nové a Končí brzy, `?prodejny=vse` dočasně ukáže akce všech prodejen (R101).
     */
    public function __invoke(Request $request, MyOffers $myOffers, OfferPresenter $presenter, MentionPresenter $mentionPresenter, LandingController $landing, CzechVocative $vocative, PriceHistory $priceHistory, WaitAdvice $waitAdvice, LocalCalendar $calendar, OfferSearch $search, ChainOverview $chainOverview): Response
    {
        // Nepřihlášený má na stejné adrese úvodní stránku (R44)
        $user = $request->user();
        if (! $user instanceof User) {
            return $landing->show();
        }

        // Vybrané prodejny (R49) — u akce, která neplatí všude, se vypíšou ty, kde platí
        $storeCodes = $user->selectedStoreCodes();
        // Dočasně všechny prodejny (R101) — na cestách; jen s vybranými prodejnami má smysl
        $allStores = $storeCodes !== [] && $request->query(self::STORES_PARAMETER) === self::ALL_STORES;
        $groups = $myOffers->forUser($user, allStores: $allStores);
        // „Je to opravdu sleva?“ (R59) — jedním dotazem pro akce všech skupin, i budoucí (R76)
        $history = $priceHistory->forOffers(array_merge(...array_map(
            fn (array $group): array => array_column([...$group['offers'], ...$group['upcoming']], 'offer'),
            $groups,
        )));
        $today = $calendar->today();
        $freshSince = $search->freshSince();
        $endingSoonBy = $today->addDays(config()->integer('letaky.offers.ending_soon_days'));
        $offerToPage = fn (array $match): array => [
            ...$presenter->toPage($match['offer'], $storeCodes, $history[$match['offer']->id] ?? null),
            'matchStatus' => $match['status']->value,
            // Cena, kterou uživatel zaplatí (s kartou, pokud ji má) — nejnižší cena
            // v hlavičce skupiny se počítá z akcí na stránce, i po výběru obchodu (R55)
            'userPrice' => $myOffers->userPrice($user, $match['offer']),
            // Štítky filtrů Nové a Končí brzy (R101) — stejná hranice jako ve Všech akcích
            'isNew' => $match['offer']->created_at !== null && $match['offer']->created_at >= $freshSince,
            'endsSoon' => ! $match['offer']->isUpcoming($today) && $match['offer']->valid_to <= $endingSoonBy,
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
                    fn (OffersSort $sort): array => ['value' => $sort->value, 'label' => $sort->shortLabel()],
                    OffersSort::cases(),
                ),
                'minDiscountPercent' => $user->min_discount_percent,
                'updateUrl' => route('account.offers-preferences', absolute: false),
            ],
            // Pohled Podle obchodů (R102): kde je která položka nejlevněji — akce jsou ve watchItems
            'byChain' => $chainOverview->build($user, $groups),
            // Dny do popisků štítků Nové a Končí brzy (R101)
            'offerFilterDays' => [
                'fresh' => config()->integer('letaky.offers.fresh_days'),
                'endingSoon' => config()->integer('letaky.offers.ending_soon_days'),
            ],
            // Vybrané prodejny (R49) a dočasné přepnutí na všechny (R101); bez vybraných null
            'stores' => $storeCodes === [] ? null : [
                'count' => count($storeCodes),
                'all' => $allStores,
                'parameter' => self::STORES_PARAMETER,
                'allValue' => self::ALL_STORES,
                'url' => route('home', absolute: false),
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
