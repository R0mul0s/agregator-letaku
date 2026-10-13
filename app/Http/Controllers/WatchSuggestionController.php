<?php

/**
 * Návrhy, co hlídat (R148): „Ne, díky“ u návrhu z nákupního seznamu. Přijetí návrhu je běžné
 * přidání hlídané položky (WatchItemController::store), které návrh samo uzavře.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shopping\WatchSuggestions;
use App\Models\WatchSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class WatchSuggestionController extends Controller
{
    /**
     * Adresy pro komponentu návrhů (WatchSuggestions.vue): přidání produktu s návratem na
     * stránku, odkud uživatel přišel, a formulář vlastních slov v Hlídám s předvyplněným textem.
     *
     * @return array{store: string, index: string, prefillParameter: string, stayField: string}
     */
    public static function pageUrls(): array
    {
        return [
            'store' => route('watch-items.store', absolute: false),
            'index' => route('watch-items.index', absolute: false),
            'prefillParameter' => WatchItemController::PREFILL_PARAMETER,
            'stayField' => WatchItemController::STAY_FIELD,
        ];
    }

    /**
     * Zavře návrh — už se neukáže; stránka zůstane, kde je.
     */
    public function destroy(WatchSuggestion $suggestion, WatchSuggestions $suggestions): RedirectResponse
    {
        Gate::authorize('delete', $suggestion);
        $suggestions->dismiss($suggestion);

        return back(fallback: route('watch-items.index'));
    }
}
