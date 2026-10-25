<?php

/**
 * Historie cen položky akce (R163) — JSON pro graf v okně s detailem akce. Načte se až po
 * otevření okna, ne s každou akcí ve výpisu (jako prodejny, R106).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\PriceHistory;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;

class OfferPriceHistoryController extends Controller
{
    /**
     * Akce stejné položky u obchodu za sledované období i s touto.
     */
    public function __invoke(Offer $offer, PriceHistory $history): JsonResponse
    {
        return response()->json([
            'weeks' => config()->integer('letaky.price_history.weeks'),
            'points' => $history->timeline($offer),
        ]);
    }
}
