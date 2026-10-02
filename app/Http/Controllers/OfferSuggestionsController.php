<?php

/**
 * Našeptávač hledání ve Všech akcích (JSON pro pole hledání).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\SearchSuggestions;
use App\Http\Requests\OffersRequest;
use Illuminate\Http\JsonResponse;

class OfferSuggestionsController extends Controller
{
    /**
     * Návrhy k hledanému textu; kratší text než minimum vrátí prázdný seznam.
     */
    public function __invoke(OffersRequest $request, SearchSuggestions $suggestions): JsonResponse
    {
        $text = $request->searchText();
        $tooShort = $text === null || mb_strlen($text) < config()->integer('letaky.offers.suggest_min_length');

        return response()->json([
            'suggestions' => $tooShort ? [] : $suggestions->for($text, $request->chain()),
        ]);
    }
}
