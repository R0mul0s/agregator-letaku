<?php

/**
 * Parametry živé ukázky hlídání na úvodní stránce (R90) — produkty katalogu oddělené čárkou
 * (`?produkty=12,7`, v pořadí výběru) a obchody stejně jako ve Všech akcích (`?chain=kaufland,lidl`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Offers\OfferFilters;
use App\Enums\Chain;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class WatchDemoRequest extends FormRequest
{
    /** Parametr adresy: ID produktů katalogu oddělená čárkou. */
    public const PRODUCTS = 'produkty';

    /** Oddělovač ID produktů. */
    private const SEPARATOR = ',';

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            self::PRODUCTS => ['nullable', 'string', 'regex:/^\d+(,\d+)*$/', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && count(explode(self::SEPARATOR, $value)) > config()->integer('letaky.landing.demo_max_products')) {
                    $fail('validation.max.array')->translate(['max' => config()->integer('letaky.landing.demo_max_products')]);
                }
            }],
            OfferFilters::CHAIN_PARAMETER => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || OffersRequest::parseChains($value) === null) {
                    $fail('validation.enum')->translate();
                }
            }],
        ];
    }

    /**
     * ID vybraných produktů v pořadí výběru, bez opakování.
     *
     * @return list<int>
     */
    public function productIds(): array
    {
        if (! $this->filled(self::PRODUCTS)) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), explode(self::SEPARATOR, $this->string(self::PRODUCTS)->toString()))));
    }

    /**
     * Vybrané obchody; prázdný seznam (bez parametru nebo „vse“) = všechny.
     *
     * @return list<Chain>
     */
    public function chains(): array
    {
        return $this->filled(OfferFilters::CHAIN_PARAMETER)
            ? OffersRequest::parseChains($this->string(OfferFilters::CHAIN_PARAMETER)->toString()) ?? []
            : [];
    }
}
