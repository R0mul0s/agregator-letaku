<?php

/**
 * Stránka „Hlídám“ — hlídané položky uživatele s přehledem aktuálních akcí a jejich úpravy (R18, R31).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogBrowseTree;
use App\Domain\Catalog\WatchableProducts;
use App\Domain\Matching\Actions\AddWatchItem;
use App\Domain\Matching\MyOffers;
use App\Domain\Matching\TextNormalizer;
use App\Domain\Matching\WatchHistory;
use App\Domain\Matching\WatchRule;
use App\Domain\Offers\UserPricing;
use App\Domain\Shopping\WatchSuggestions;
use App\Enums\AnalyticsEvent;
use App\Enums\MatchStatus;
use App\Http\Requests\WatchItemRequest;
use App\Models\Offer;
use App\Models\User;
use App\Models\WatchItem;
use App\Rules\KeywordFields;
use App\Support\AnalyticsEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WatchItemController extends Controller
{
    /** Parametr adresy Hlídám, který otevře úpravu položky (odkaz z Mých slev). */
    public const EDIT_PARAMETER = 'upravit';

    /** Parametr adresy Hlídám, který otevře formulář vlastních slov s textem (z karty akce, R60). */
    public const PREFILL_PARAMETER = 'pridat';

    /** Pole formuláře: po přidání zůstat na stránce, odkud uživatel přišel (Všechny akce, R60). */
    public const STAY_FIELD = 'stay';

    /** Kódy stavu pro toast po uložení (R47, lang: ui.toast.messages). */
    public const STATUS_ADDED = 'watch-item-added';

    public const STATUS_UPDATED = 'watch-item-updated';

    public const STATUS_REMOVED = 'watch-item-removed';

    /** Session: adresa smazání právě přidané položky pro „Vrátit“ v toastu (R71, sdílená statusUndo). */
    public const UNDO_SESSION_KEY = 'status_undo';

    /**
     * Hlídané položky s tím, co k nim teď je v akci (počet akcí, nejnižší cena, zmínky
     * v letácích — stejně jako v Mých slevách), u položky bez akce kdy naposledy v akci byla
     * (R152), a produkty katalogu k přidání.
     */
    public function index(Request $request, WatchableProducts $watchable, CatalogBrowseTree $browseTree, MyOffers $myOffers, UserPricing $pricing, WatchSuggestions $suggestions, WatchHistory $history): Response
    {
        /** @var User $user */
        $user = $request->user();
        $products = $watchable->all();

        return Inertia::render('WatchItems', [
            'urls' => [
                'store' => route('watch-items.store', absolute: false),
                'home' => route('home', absolute: false),
                'preview' => route('watch-items.preview', absolute: false),
                // Bez hlídaných položek nabídne prázdný stav průvodce prvním nastavením (R153)
                'onboarding' => route('onboarding', absolute: false),
            ],
            'watchItems' => array_map(fn (array $group): array => [
                ...$this->itemToPage($group['watchItem']),
                'offersCount' => count($group['offers']),
                'mentionsCount' => count($group['mentions']),
                'lowestPrice' => $pricing->lowest($user, array_column($group['offers'], 'offer')),
                // Teď bez akce (R152): kdy naposledy v akci byla, z kteréhokoli obchodu jako náhled (R104)
                'lastSeen' => $group['offers'] === [] ? $this->lastSeenToPage($user, $pricing, $history, $history->lastSeenForItem($group['watchItem'], $group['rule'])) : null,
            ], $myOffers->forUser($user)),
            // Odkaz „Upravit“ z Mých slev (?upravit=id) otevře úpravu položky rovnou v dlaždici
            'editId' => $request->integer(self::EDIT_PARAMETER) ?: null,
            // „Hlídat“ u akce bez produktu katalogu (R60) — text jako název i hledaná slova
            'prefill' => Str::limit(trim($request->string(self::PREFILL_PARAMETER)->toString()), config()->integer('letaky.watch.name_max_length'), '') ?: null,
            // Katalog nahradil šablony (R31) — produkt jde hlídat jedním klepnutím, nejvýš jednou;
            // našeptávač ukáže, jestli se hlídání vyplatí — kolik akcí by produkt teď našel (R71)
            'products' => $watchable->toPage($user, $products),
            // Procházení katalogu po odděleních jako v e-shopu (R47)
            'catalogTree' => $browseTree->build($products),
            // Návrhy, co hlídat (R148): z nákupního seznamu ze serveru, z opakovaného hledání
            // v prohlížeči (lib/search.js) se stejnými hranicemi
            'watchSuggestions' => [
                'items' => $suggestions->forUser($user),
                'urls' => WatchSuggestionController::pageUrls(),
                'searchMinHits' => config()->integer('letaky.watch_suggestions.min_hits'),
                'searchWindowDays' => config()->integer('letaky.watch_suggestions.window_days'),
                'maxShown' => config()->integer('letaky.watch_suggestions.max_shown'),
            ],
        ]);
    }

    /**
     * Položka pro stránku: pravidla a adresy úprav.
     *
     * @return array<string, mixed>
     */
    private function itemToPage(WatchItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'productId' => $item->product_id,
            'productName' => $item->product?->name,
            'keywords' => $item->keywords,
            'variantKeywords' => $item->variant_keywords,
            'excludeKeywords' => $item->exclude_keywords,
            'updateUrl' => route('watch-items.update', $item, absolute: false),
            'deleteUrl' => route('watch-items.destroy', $item, absolute: false),
        ];
    }

    /**
     * Založí položku.
     */
    public function store(WatchItemRequest $request, AddWatchItem $add): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $item = $add($user, $request->watchItemData());
        AnalyticsEvents::flash($request, AnalyticsEvent::AddWatchItem, ['item_type' => $item->product_id === null ? 'keywords' : 'product']);

        // Z karty ve Všech akcích (R60) zpátky na stejné místo, jinak na Hlídám. Toast nabídne
        // „Vrátit“ — smazání právě přidané položky (R71)
        return ($request->boolean(self::STAY_FIELD) ? back(fallback: route('watch-items.index')) : to_route('watch-items.index'))
            ->with('status', self::STATUS_ADDED)
            ->with(self::UNDO_SESSION_KEY, route('watch-items.destroy', $item, absolute: false));
    }

    /**
     * Náhled vlastních slov (R71): kolik akcí by položka teď našla a pár příkladů — uživatel
     * hned vidí, že „rum“ chytá i „Rump steak“. Stejná pravidla jako Moje slevy. Když teď
     * nenajde nic, přidá poslední akci z historie, nebo od kdy akce sledujeme (R104).
     */
    public function preview(Request $request, MyOffers $myOffers, UserPricing $pricing, WatchHistory $history, TextNormalizer $normalizer): JsonResponse
    {
        $data = $request->validate(KeywordFields::rules());

        /** @var User $user */
        $user = $request->user();
        $rule = WatchRule::fromText($data['keywords'], $data['variant_keywords'] ?? null, $data['exclude_keywords'] ?? null, $normalizer);
        $matches = $myOffers->preview($user, $rule);
        $lastSeen = $matches === [] ? $history->lastSeen($rule) : null;

        return response()->json([
            'count' => count($matches),
            'examples' => array_map(fn (array $match): array => [
                'name' => $match['offer']->name,
                'chain' => $match['offer']->chain->value,
                'price' => $pricing->price($user, $match['offer']),
                'maybe' => $match['status'] === MatchStatus::Maybe,
            ], array_slice($matches, 0, config()->integer('letaky.search.preview_examples'))),
            'lastSeen' => $this->lastSeenToPage($user, $pricing, $history, $lastSeen),
            'trackingSince' => $matches === [] && $lastSeen === null ? $history->trackingSince()?->toDateString() : null,
        ]);
    }

    /**
     * Poslední akce z historie (R104, R152) pro náhled a dlaždici: název, obchod, cena, kterou
     * by uživatel zaplatil, a poslední den platnosti.
     *
     * @return array{name: string, chain: string, price: int|null, endedOn: string}|null
     */
    private function lastSeenToPage(User $user, UserPricing $pricing, WatchHistory $history, ?Offer $offer): ?array
    {
        return $offer === null ? null : [
            'name' => $offer->name,
            'chain' => $offer->chain->value,
            'price' => $pricing->price($user, $offer),
            'endedOn' => $history->endedOn($offer)->toDateString(),
        ];
    }

    /**
     * Uloží změny položky.
     */
    public function update(WatchItemRequest $request, WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('update', $watchItem);
        $watchItem->update($request->watchItemData());

        return to_route('watch-items.index')->with('status', self::STATUS_UPDATED);
    }

    /**
     * Smaže položku a vrátí se na stránku, odkud uživatel přišel (Hlídám, nebo Moje slevy).
     */
    public function destroy(WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('delete', $watchItem);
        $watchItem->delete();

        return back(fallback: route('watch-items.index'))->with('status', self::STATUS_REMOVED);
    }
}
