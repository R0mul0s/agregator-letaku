<?php

/**
 * Průvodce prvním nastavením (R153), krok Karty: věrnostní karty, které uživatel má — celý stav
 * po každém přepnutí (R64).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LoyaltyProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingCardsRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loyalty_programs' => ['present', 'array'],
            'loyalty_programs.*' => ['distinct', Rule::enum(LoyaltyProgram::class)],
        ];
    }

    /**
     * Věrnostní karty, které uživatel má.
     *
     * @return list<LoyaltyProgram>
     */
    public function loyaltyPrograms(): array
    {
        return array_values(array_map(LoyaltyProgram::from(...), $this->validated('loyalty_programs')));
    }
}
