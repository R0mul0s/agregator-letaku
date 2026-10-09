<?php

/**
 * „Tohle ne“ (R125) — skrytí akce u hlídané položky a vyloučení slova z názvu akce, i vrácení
 * obojího. Z okna akce (pole `inline`) bez toastu — okno zůstane otevřené a potvrdí to samo;
 * jinak toast, u skrytí s „Vrátit“ (přehled skrytých v Mých slevách).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Matching\TextNormalizer;
use App\Http\Requests\ExcludeWordRequest;
use App\Models\Offer;
use App\Models\WatchItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WatchItemExclusionController extends Controller
{
    /** Pole požadavku: odesláno z okna akce, potvrzení ukáže okno, ne toast (R125). */
    public const INLINE_FIELD = 'inline';

    /** Kódy stavu pro toast (R47, lang: ui.toast.messages). */
    public const STATUS_OFFER_HIDDEN = 'offer-hidden';

    public const STATUS_OFFER_RESTORED = 'offer-restored';

    public const STATUS_WORD_EXCLUDED = 'word-excluded';

    public const STATUS_WORD_RESTORED = 'word-restored';

    /**
     * Skryje akci u položky.
     */
    public function hideOffer(Request $request, WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('update', $watchItem);
        $data = $request->validate(['offer_id' => ['required', 'integer', Rule::exists('offers', 'id')]]);
        $watchItem->offerExclusions()->firstOrCreate(['offer_id' => $data['offer_id']]);

        return self::done($request, self::STATUS_OFFER_HIDDEN, route('watch-items.hidden-offers.destroy', [$watchItem, $data['offer_id']], absolute: false));
    }

    /**
     * Vrátí skrytou akci zpět do položky.
     */
    public function restoreOffer(Request $request, WatchItem $watchItem, Offer $offer): RedirectResponse
    {
        Gate::authorize('update', $watchItem);
        $watchItem->offerExclusions()->where('offer_id', $offer->id)->delete();

        return self::done($request, self::STATUS_OFFER_RESTORED);
    }

    /**
     * Přidá slovo mezi vyloučená slova položky (u položky z katalogu navíc k pravidlům
     * produktu); slovo, které už vyloučené je, nepřidá znovu.
     */
    public function excludeWord(ExcludeWordRequest $request, WatchItem $watchItem, TextNormalizer $normalizer): RedirectResponse
    {
        $word = $request->word();
        $words = $this->words($watchItem->exclude_keywords);
        if (! in_array($normalizer->word($word), array_map($normalizer->word(...), $words), true)) {
            $watchItem->update(['exclude_keywords' => implode(' ', [...$words, $word])]);
        }

        return self::done($request, self::STATUS_WORD_EXCLUDED, route('watch-items.excluded-words.destroy', [$watchItem, $word], absolute: false));
    }

    /**
     * Odebere slovo z vyloučených slov položky (bez ohledu na diakritiku a velká písmena).
     */
    public function restoreWord(Request $request, WatchItem $watchItem, string $word, TextNormalizer $normalizer): RedirectResponse
    {
        Gate::authorize('update', $watchItem);
        $removed = $normalizer->word($word);
        $words = array_values(array_filter(
            $this->words($watchItem->exclude_keywords),
            fn (string $excluded): bool => $normalizer->word($excluded) !== $removed,
        ));
        $watchItem->update(['exclude_keywords' => $words === [] ? null : implode(' ', $words)]);

        return self::done($request, self::STATUS_WORD_RESTORED);
    }

    /**
     * Zpět na stránku; z okna akce bez toastu, jinak s kódem stavu a případně adresou pro
     * „Vrátit“ v toastu. Sdílí ho hlášení chyby (OfferReportController).
     */
    public static function done(Request $request, string $status, ?string $undoUrl = null): RedirectResponse
    {
        $response = back(fallback: route('home'));
        if ($request->boolean(self::INLINE_FIELD)) {
            return $response;
        }

        $response->with('status', $status);

        return $undoUrl === null ? $response : $response->with(WatchItemController::UNDO_SESSION_KEY, $undoUrl);
    }

    /**
     * Slova zápisu vyloučení oddělená mezerou (alternativy „a|b“ zůstanou jedním slovem).
     *
     * @return list<string>
     */
    private function words(?string $text): array
    {
        return preg_split('/\s+/u', trim((string) $text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
