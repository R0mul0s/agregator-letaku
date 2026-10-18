<?php

/**
 * Průvodce prvním nastavením (R153), krok Obchody: které obchody uživatel sleduje — celý stav
 * po každém přepnutí (R64). Sledovat jde jen obchod se zdrojem nabídek.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Chains\ChainCatalog;
use App\Enums\Chain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingChainsRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(ChainCatalog $catalog): array
    {
        return [
            'chains' => ['present', 'array'],
            'chains.*' => ['distinct', Rule::in(array_map(fn (Chain $chain): string => $chain->value, $catalog->available()))],
        ];
    }

    /**
     * Sledované obchody.
     *
     * @return list<Chain>
     */
    public function chains(): array
    {
        return array_values(array_map(Chain::from(...), $this->validated('chains')));
    }
}
