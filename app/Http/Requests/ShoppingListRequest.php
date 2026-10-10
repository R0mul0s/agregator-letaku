<?php

/**
 * Přidání nebo odebrání akce v nákupním seznamu (R61) — existující akce a nejvýš
 * `letaky.shopping_list.max_items` položek na uživatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Shopping\ShoppingQuantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShoppingListRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer', Rule::exists('offers', 'id')],
            // Množství při přidání z pole „Co koupit“ („2x cola“, R133); karta akce ho neposílá
            'quantity' => ShoppingQuantity::rules(),
        ];
    }

    /**
     * Množství nově přidané akce (R133); bez něj 1.
     */
    public function quantity(): int
    {
        return $this->integer('quantity', ShoppingQuantity::DEFAULT);
    }

    /**
     * ID akce k přidání.
     */
    public function offerId(): int
    {
        return $this->integer('offer_id');
    }

    /**
     * Seznam nad limit položek nepřidá; odebrat jde vždy.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->user()?->shoppingListItems();
                $limit = config()->integer('letaky.shopping_list.max_items');
                if ($items !== null && ! (clone $items)->where('offer_id', $this->offerId())->exists() && $items->count() >= $limit) {
                    $validator->errors()->add('offer_id', __('app.ui.shopping.limit', ['count' => $limit]));
                }
            },
        ];
    }
}
