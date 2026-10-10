<?php

/**
 * Nákupní seznam pro stránku (R61, R130) — položky po obchodech v pořadí výčtu, vlastní
 * položky bez obchodu nakonec do skupiny „Kdekoli“. V obchodě nejdřív, co zbývá koupit,
 * z toho nejdřív akce, které už platí — budoucí (R76) se zatím za akční cenu koupit nedají.
 *
 * Sdílí ho stránka vlastníka seznamu i sdílený odkaz (R130) — liší se jen adresami odškrtnutí
 * a smazání; ceny jsou vždy vlastníka (jeho karty, R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Shopping;

use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\OfferPresenter;
use App\Domain\Offers\UserPricing;
use App\Enums\Chain;
use App\Models\ShoppingListItem;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;

final readonly class ShoppingListView
{
    /** Klíč skupiny vlastních položek bez obchodu. */
    public const ANYWHERE = 'anywhere';

    public function __construct(
        private OfferPresenter $presenter,
        private UserPricing $pricing,
        private LocalCalendar $calendar,
    ) {}

    /**
     * Položky seznamu.
     *
     * @return Collection<int, ShoppingListItem>
     */
    public function items(User $owner): Collection
    {
        return $owner->shoppingListItems()->with(['offer' => fn ($query) => $query->withoutRaw(), 'offer.stores'])->get();
    }

    /**
     * Skupiny po obchodech pro stránku.
     *
     * @param  Collection<int, ShoppingListItem>  $items
     * @param  Closure(ShoppingListItem): string  $updateUrl  Adresa odškrtnutí
     * @param  (Closure(ShoppingListItem): string)|null  $deleteUrl  Adresa smazání; null = mazat nejde (sdílený odkaz)
     * @return list<array{chain: string, chainName: string, items: list<array<string, mixed>>}>
     */
    public function groups(User $owner, Collection $items, Closure $updateUrl, ?Closure $deleteUrl): array
    {
        $today = $this->calendar->today();
        $storeCodes = $owner->selectedStoreCodes();

        $groups = [];
        foreach ([...Chain::cases(), null] as $chain) {
            $inGroup = $items
                ->filter(fn (ShoppingListItem $item): bool => $item->storeChain() === $chain)
                ->sortBy(fn (ShoppingListItem $item): array => [
                    $item->checked_at !== null,
                    $item->offer?->isUpcoming($today) ?? false,
                    mb_strtolower($item->displayName()),
                ])
                ->values();
            if ($inGroup->isEmpty()) {
                continue;
            }

            $groups[] = [
                'chain' => $chain === null ? self::ANYWHERE : $chain->value,
                'chainName' => $chain === null ? (string) __('app.ui.shopping.anywhere') : $chain->label(),
                'items' => array_values($inGroup->map(fn (ShoppingListItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->displayName(),
                    'checked' => $item->checked_at !== null,
                    'expired' => $item->offer !== null && $item->offer->valid_to->lessThan($today),
                    'userPrice' => $item->offer === null ? null : $this->pricing->price($owner, $item->offer),
                    // Vlastní položka (R130) akci nemá
                    'offer' => $item->offer === null ? null : $this->presenter->toPage($item->offer, $storeCodes),
                    'updateUrl' => $updateUrl($item),
                    'deleteUrl' => $deleteUrl === null ? null : $deleteUrl($item),
                ])->all()),
            ];
        }

        return $groups;
    }
}
