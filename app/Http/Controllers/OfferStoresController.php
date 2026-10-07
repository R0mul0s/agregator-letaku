<?php

/**
 * Prodejny, ve kterých akce platí (R49) — JSON pro okno „Kde akce platí“. Načte se až po
 * otevření okna, ne s každou akcí ve výpisu (R106).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\OfferPresenter;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferStoresController extends Controller
{
    /**
     * Seznam prodejen akce; přihlášenému označí jeho vybrané prodejny.
     */
    public function __invoke(Request $request, Offer $offer, OfferPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        $selected = $user instanceof User ? $user->selectedStoreCodes() : [];

        return response()->json(['stores' => $presenter->storeList($offer, $selected)]);
    }
}
