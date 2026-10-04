<?php

/**
 * Odškrtnutí v nákupním seznamu udělaná bez připojení (R66): seznam [{ id, checked }],
 * který prohlížeč pošle najednou, až je zase signál.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShoppingListSyncRequest extends FormRequest
{
    /**
     * Pravidla validace — víc změn, než se vejde položek do seznamu, poslat nejde.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'checks' => ['required', 'array', 'max:'.config()->integer('letaky.shopping_list.max_items')],
            'checks.*.id' => ['required', 'integer'],
            'checks.*.checked' => ['required', 'boolean'],
        ];
    }

    /**
     * Id položek podle nového stavu: odškrtnuté a vrácené mezi nekoupené.
     *
     * @return array{checked: list<int>, unchecked: list<int>}
     */
    public function changes(): array
    {
        $changes = ['checked' => [], 'unchecked' => []];
        /** @var list<array{id: int|string, checked: bool|int|string}> $checks */
        $checks = $this->validated('checks');
        foreach ($checks as $check) {
            $changes[filter_var($check['checked'], FILTER_VALIDATE_BOOLEAN) ? 'checked' : 'unchecked'][] = (int) $check['id'];
        }

        return $changes;
    }
}
