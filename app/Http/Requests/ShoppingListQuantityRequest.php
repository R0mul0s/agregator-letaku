<?php

/**
 * Změna množství položky nákupního seznamu (R133) — počet kusů nebo balení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Shopping\ShoppingQuantity;
use Illuminate\Foundation\Http\FormRequest;

class ShoppingListQuantityRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['quantity' => ShoppingQuantity::rules(required: true)];
    }

    /**
     * Nové množství.
     */
    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
