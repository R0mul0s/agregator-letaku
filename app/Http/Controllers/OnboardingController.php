<?php

/**
 * Průvodce prvním nastavením nového účtu `/vitejte` (R153): 1. co kupuje (produkty katalogu
 * a vlastní slova), 2. kde nakupuje, 3. věrnostní karty, 4. jak mu dáme vědět, pak Hotovo.
 * Každá volba se uloží hned (R63, R64) bez toastu — stav dlaždice nebo přepínače je potvrzení
 * sám a toasty by při klepání zakrývaly počítadlo akcí. Souhrn e-mailem a upozornění v telefonu
 * ukládají stejné adresy jako Můj účet. Krok je v adrese (`?krok=2`), ať jde průvodce otevřít
 * na kroku z karty „Dokončete nastavení“ na Mých slevách.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\Onboarding;
use App\Domain\Catalog\WatchableProducts;
use App\Domain\Chains\Actions\FollowChains;
use App\Domain\Chains\ChainCatalog;
use App\Domain\Matching\Actions\AddWatchItem;
use App\Domain\Push\Vapid;
use App\Enums\AnalyticsEvent;
use App\Enums\DigestFrequency;
use App\Enums\LoyaltyProgram;
use App\Http\Requests\DigestRequest;
use App\Http\Requests\OnboardingCardsRequest;
use App\Http\Requests\OnboardingChainsRequest;
use App\Http\Requests\WatchItemRequest;
use App\Models\FollowedChain;
use App\Models\User;
use App\Models\WatchItem;
use App\Support\AnalyticsEvents;
use App\Support\CzechVocative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /** Parametr adresy s krokem průvodce. */
    public const STEP_PARAMETER = 'krok';

    /** Kroky v adrese v pořadí průvodce; poslední je obrazovka Hotovo. */
    public const STEPS = ['1', '2', '3', '4', self::STEP_DONE];

    public const STEP_DONE = 'hotovo';

    /**
     * Zobrazí krok průvodce. Drahé údaje jsou closure — po klepnutí si stránka načte jen ty,
     * které se mění (částečné načtení Inertie).
     */
    public function show(Request $request, Onboarding $onboarding, WatchableProducts $watchable, ChainCatalog $catalog, CzechVocative $vocative, Vapid $vapid): Response
    {
        $user = $this->user($request);
        $step = $request->query(self::STEP_PARAMETER);

        return Inertia::render('Onboarding', [
            'step' => is_string($step) && in_array($step, self::STEPS, true) ? $step : self::STEPS[0],
            'steps' => self::STEPS,
            'stepParameter' => self::STEP_PARAMETER,
            // Oslovení „Vítejte, Romane!“ — křestní jméno v 5. pádě (R47)
            'greetingName' => $vocative->firstName($user->name),
            'urls' => [
                'step' => route('onboarding', absolute: false),
                'items' => route('onboarding.items.store', absolute: false),
                'chains' => route('onboarding.chains', absolute: false),
                'cards' => route('onboarding.cards', absolute: false),
                'finish' => route('onboarding.finish', absolute: false),
                'digest' => route('account.digest', absolute: false),
                'preview' => route('watch-items.preview', absolute: false),
                'home' => route('home', absolute: false),
                'watchItems' => route('watch-items.index', absolute: false),
                'preferences' => route('preferences', absolute: false),
                'account' => route('account', absolute: false),
            ],
            'minItems' => config()->integer('letaky.onboarding.min_items'),
            'watchItems' => fn (): array => array_values($user->watchItems()->orderBy('id')->get()->map(fn (WatchItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'productId' => $item->product_id,
                'deleteUrl' => route('onboarding.items.destroy', $item, absolute: false),
            ])->all()),
            // Celý katalog pro hledání (WatchAdd) a vybrané produkty běžného nákupu po odděleních
            'products' => fn (): array => $watchable->toPage($user, $watchable->all()),
            'featured' => fn (): array => $onboarding->featuredProducts(),
            'chains' => fn (): array => $this->chains($user, $onboarding, $catalog),
            'loyaltyPrograms' => fn (): array => $user->loyalty_programs?->map(fn (LoyaltyProgram $program): string => $program->value)->values()->all() ?? [],
            // Počítadlo dole: kolik akcí s nastavením teď v Mých slevách uvidí
            'offersCount' => fn (): int => $onboarding->offersCount($user),
            'digest' => fn (): array => [
                'frequency' => $user->digest_frequency->value,
                'options' => array_map(
                    fn (DigestFrequency $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()],
                    DigestFrequency::cases(),
                ),
                'errorBag' => DigestRequest::ERROR_BAG,
            ],
            'emailVerified' => $user->email_verified_at !== null,
            'email' => $user->email,
            // Upozornění v telefonu (R66) — stejné jako Můj účet
            'push' => fn (): ?array => PushSubscriptionController::pageSettings($user, $vapid),
        ]);
    }

    /**
     * Začne hlídat produkt katalogu nebo vlastní slova (dlaždice a hledání v prvním kroku).
     */
    public function storeItem(WatchItemRequest $request, AddWatchItem $add): RedirectResponse
    {
        $item = $add($this->user($request), $request->watchItemData());
        AnalyticsEvents::flash($request, AnalyticsEvent::AddWatchItem, ['item_type' => $item->product_id === null ? 'keywords' : 'product']);

        return back(fallback: route('onboarding'));
    }

    /**
     * Přestane hlídat položku (druhé klepnutí na dlaždici, křížek u vybrané položky).
     */
    public function destroyItem(WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('delete', $watchItem);
        $watchItem->delete();

        return back(fallback: route('onboarding'));
    }

    /**
     * Uloží sledované obchody; obchod sledovaný dál si upřesnění nechá.
     */
    public function updateChains(OnboardingChainsRequest $request, FollowChains $follow): RedirectResponse
    {
        $follow($this->user($request), $request->chains());

        return back(fallback: route('onboarding'));
    }

    /**
     * Uloží věrnostní karty.
     */
    public function updateCards(OnboardingCardsRequest $request): RedirectResponse
    {
        $this->user($request)->update(['loyalty_programs' => $request->loyaltyPrograms()]);

        return back(fallback: route('onboarding'));
    }

    /**
     * Průvodce je za uživatelem: dokončený (obrazovka Hotovo), nebo zavřená karta „Dokončete
     * nastavení“ na Mých slevách (`dismissed`, zpět na stránku).
     */
    public function finish(Request $request, Onboarding $onboarding): RedirectResponse
    {
        $dismissed = $request->boolean('dismissed');
        $onboarding->finish($this->user($request));
        AnalyticsEvents::flash($request, AnalyticsEvent::CompleteOnboarding, ['result' => $dismissed ? 'dismissed' : 'finished']);

        return $dismissed
            ? back(fallback: route('home'))
            : to_route('onboarding', [self::STEP_PARAMETER => self::STEP_DONE]);
    }

    /**
     * Obchody kroku 2 a 3: sledovaný, počet akcí, které dnes platí, a karta s počtem akcí jen
     * pro její držitele.
     *
     * @return list<array{value: string, followed: bool, offersCount: int, loyaltyProgram: string|null, loyaltyProgramName: string|null, loyaltyOnlyCount: int}>
     */
    private function chains(User $user, Onboarding $onboarding, ChainCatalog $catalog): array
    {
        $followed = $user->followedChains()->get()->map(fn (FollowedChain $chain): string => $chain->chain->value)->all();

        return array_map(fn (array $stats): array => [
            'value' => $stats['chain']->value,
            'followed' => in_array($stats['chain']->value, $followed, true),
            'offersCount' => $stats['offers'],
            'loyaltyProgram' => $catalog->loyaltyProgram($stats['chain'])?->value,
            'loyaltyProgramName' => $catalog->loyaltyProgram($stats['chain'])?->label(),
            'loyaltyOnlyCount' => $stats['loyaltyOnly'],
        ], $onboarding->chainStats());
    }
}
