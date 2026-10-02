<?php

/**
 * Formulář „Moje obchody“ — sledované obchody s upřesněním a věrnostní karty (R19, R21).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Chains\ChainCatalog;
use App\Domain\Chains\FollowedChainData;
use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\StoreFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShoppingPreferencesRequest extends FormRequest
{
    /**
     * Pravidla validace. Sledovat jde jen obchod se zdrojem nabídek.
     *
     * @return array<string, mixed>
     */
    public function rules(ChainCatalog $catalog): array
    {
        return [
            'chains' => ['present', 'array'],
            'chains.*.chain' => ['required', 'distinct', Rule::in(array_map(fn (Chain $chain): string => $chain->value, $catalog->available()))],
            'chains.*.store_format' => ['nullable', Rule::enum(StoreFormat::class)],
            'chains.*.include_online_only' => ['required', 'boolean'],
            'loyalty_programs' => ['present', 'array'],
            'loyalty_programs.*' => ['distinct', Rule::enum(LoyaltyProgram::class)],
        ];
    }

    /**
     * Sledované obchody.
     *
     * @return list<FollowedChainData>
     */
    public function followedChains(): array
    {
        return array_values(array_map(fn (array $chain): FollowedChainData => new FollowedChainData(
            Chain::from($chain['chain']),
            isset($chain['store_format']) ? StoreFormat::from($chain['store_format']) : null,
            (bool) $chain['include_online_only'],
        ), $this->validated('chains')));
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
