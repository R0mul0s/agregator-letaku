<?php

/**
 * Parametry přehledu nabídek — hledaný text, obchod a načtené stránky (R43).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Chain;
use App\Http\Requests\Concerns\HasPageWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OffersRequest extends FormRequest
{
    use HasPageWindow;

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.config()->integer('letaky.offers.search_max_length')],
            'chain' => ['nullable', Rule::enum(Chain::class)],
            ...$this->pageWindowRules(),
        ];
    }

    /**
     * Hledaný text; prázdný je null.
     */
    public function searchText(): ?string
    {
        $text = trim($this->string('q')->toString());

        return $text === '' ? null : $text;
    }

    /**
     * Zvolený obchod.
     */
    public function chain(): ?Chain
    {
        return $this->enum('chain', Chain::class);
    }
}
