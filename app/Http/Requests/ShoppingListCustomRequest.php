<?php

/**
 * Vlastní položka nákupního seznamu bez akce (R130) — název („Almette“) a nepovinně obchod,
 * kde ji koupit; nejvýš `letaky.shopping_list.max_items` položek na uživatele jako u akcí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Shopping\ShoppingQuantity;
use App\Enums\Chain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShoppingListCustomRequest extends FormRequest
{
    /** Nejdelší název vlastní položky (sloupec custom_name). */
    public const NAME_MAX_LENGTH = 100;

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_LENGTH],
            'chain' => ['nullable', Rule::enum(Chain::class)],
            'quantity' => ShoppingQuantity::rules(),
        ];
    }

    /**
     * Množství (R133); bez něj 1.
     */
    public function quantity(): int
    {
        return $this->integer('quantity', ShoppingQuantity::DEFAULT);
    }

    /**
     * Název bez mezer navíc.
     */
    public function itemName(): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($this->string('name')->toString()));
    }

    /**
     * Obchod, kde položku koupit; null = kdekoli.
     */
    public function chain(): ?Chain
    {
        return $this->enum('chain', Chain::class);
    }

    /**
     * Názvy polí v chybách.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => __('app.ui.shopping.add_field')];
    }

    /**
     * Seznam nad limit položek nepřidá.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $limit = config()->integer('letaky.shopping_list.max_items');
                if (($this->user()?->shoppingListItems()->count() ?? 0) >= $limit) {
                    $validator->errors()->add('name', __('app.ui.shopping.limit', ['count' => $limit]));
                }
            },
        ];
    }
}
