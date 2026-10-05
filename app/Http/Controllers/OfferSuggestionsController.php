<?php

/**
 * Našeptávač hledání ve Všech akcích (JSON pro pole hledání, R71).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\OfferFilters;
use App\Domain\Offers\SearchSuggestions;
use App\Http\Requests\OffersRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class OfferSuggestionsController extends Controller
{
    /**
     * Návrhy k hledanému textu; prázdné pole dostane oblíbené produkty, text kratší než
     * minimum nic.
     */
    public function __invoke(OffersRequest $request, SearchSuggestions $suggestions): JsonResponse
    {
        $text = $request->searchText();
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        // Návrhy jen z vybraných obchodů a bez e-shopu jako výsledky; produkt ani „brzy“ je nezužují
        $filters = new OfferFilters($request->chains(), withoutEshop: $request->withoutEshop());

        if ($text === null) {
            return response()->json(['corrected' => null, 'total' => 0, 'products' => $suggestions->popular($filters, $user), 'offers' => [], 'popular' => true]);
        }
        if (mb_strlen($text) < config()->integer('letaky.offers.suggest_min_length')) {
            return response()->json(['corrected' => null, 'total' => 0, 'products' => [], 'offers' => [], 'popular' => false]);
        }

        return response()->json([...$suggestions->for($text, $filters, $user), 'popular' => false]);
    }
}
