<?php

/**
 * Stránka „Moje obchody“ — co uživatel sleduje a jaké má karty (R19, R21).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Chains\Actions\UpdateShoppingPreferences;
use App\Domain\Chains\ChainCatalog;
use App\Enums\Chain;
use App\Enums\StoreFormat;
use App\Http\Requests\UpdateShoppingPreferencesRequest;
use App\Models\FollowedChain;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShoppingPreferencesController extends Controller
{
    /** Kód stavu po uložení (lang: ui.preferences.saved). */
    private const STATUS_SAVED = 'preferences-saved';

    /**
     * Zobrazí všechny obchody — sledovatelné s upřesněním, ostatní jako připravované.
     */
    public function show(Request $request, ChainCatalog $catalog): Response
    {
        /** @var User $user */
        $user = $request->user();
        $followed = $user->followedChains()->get()->keyBy(fn (FollowedChain $chain): string => $chain->chain->value);
        $available = $catalog->available();

        return Inertia::render('Preferences', [
            'urls' => ['update' => route('preferences.update', absolute: false)],
            'chains' => array_map(fn (Chain $chain): array => [
                'value' => $chain->value,
                'name' => $chain->label(),
                'available' => in_array($chain, $available, true),
                'followed' => $followed->has($chain->value),
                'storeFormat' => $followed->get($chain->value)?->store_format?->value,
                'includeOnlineOnly' => $followed->get($chain->value)->include_online_only ?? true,
                'hasStoreFormats' => $catalog->hasStoreFormats($chain),
                'hasEshop' => $catalog->hasEshop($chain),
                'loyaltyProgram' => $catalog->loyaltyProgram($chain)?->value,
                'loyaltyProgramName' => $catalog->loyaltyProgram($chain)?->label(),
            ], Chain::cases()),
            'storeFormats' => array_map(fn (StoreFormat $format): array => [
                'value' => $format->value,
                'name' => $format->label(),
            ], StoreFormat::cases()),
            'loyaltyPrograms' => $user->loyalty_programs?->map->value->values() ?? [],
        ]);
    }

    /**
     * Uloží nastavení a vrátí se na stránku se zprávou.
     */
    public function update(UpdateShoppingPreferencesRequest $request, UpdateShoppingPreferences $update): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $update($user, $request->followedChains(), $request->loyaltyPrograms());

        return to_route('preferences')->with('status', self::STATUS_SAVED);
    }
}
