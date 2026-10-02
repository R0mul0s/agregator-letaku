<?php

/**
 * Stránka „Hlídám“ — hlídané položky uživatele a jejich úpravy (R18).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WatchItemRequest;
use App\Models\User;
use App\Models\WatchItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WatchItemController extends Controller
{
    /**
     * Seznam položek, formulář nové položky a šablony.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('WatchItems', [
            'urls' => ['store' => route('watch-items.store', absolute: false)],
            'watchItems' => $user->watchItems()->orderBy('name')->get()->map(fn (WatchItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'keywords' => $item->keywords,
                'variantKeywords' => $item->variant_keywords,
                'excludeKeywords' => $item->exclude_keywords,
                'updateUrl' => route('watch-items.update', $item, absolute: false),
                'deleteUrl' => route('watch-items.destroy', $item, absolute: false),
            ]),
            'templates' => collect(config()->array('letaky.watch.templates'))->map(fn (array $template, string $key): array => [
                'key' => $key,
                'name' => __('app.ui.watch.templates.'.$key),
                'keywords' => $template['keywords'],
                'variantKeywords' => $template['variant_keywords'],
                'excludeKeywords' => $template['exclude_keywords'],
            ])->values(),
        ]);
    }

    /**
     * Založí položku.
     */
    public function store(WatchItemRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->watchItems()->create($request->validated());

        return to_route('watch-items.index');
    }

    /**
     * Uloží změny položky.
     */
    public function update(WatchItemRequest $request, WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('update', $watchItem);
        $watchItem->update($request->validated());

        return to_route('watch-items.index');
    }

    /**
     * Smaže položku.
     */
    public function destroy(WatchItem $watchItem): RedirectResponse
    {
        Gate::authorize('delete', $watchItem);
        $watchItem->delete();

        return to_route('watch-items.index');
    }
}
