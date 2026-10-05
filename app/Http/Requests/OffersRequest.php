<?php

/**
 * Parametry přehledu nabídek — hledaný text, obchody, produkt katalogu (R71), jen budoucí
 * akce (R76), bez akcí jen z e-shopu (R82) a načtené stránky (R43).
 *
 * Obchody jsou v parametru `chain` oddělené čárkou (`?chain=kaufland,lidl`), jeden obchod
 * má stejnou adresu jako dřív (indexované `?chain=kaufland`, R45). Bez parametru vidí
 * přihlášený své sledované obchody, nepřihlášený všechny; `?chain=vse` jsou vždy všechny.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Offers\OfferFilters;
use App\Enums\Chain;
use App\Http\Requests\Concerns\HasPageWindow;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OffersRequest extends FormRequest
{
    use HasPageWindow;

    /** Parametr adresy: obchody oddělené čárkou. */
    public const CHAIN = OfferFilters::CHAIN_PARAMETER;

    /** Parametr adresy: akce jednoho produktu katalogu (z našeptávače, R71). */
    public const PRODUCT = OfferFilters::PRODUCT_PARAMETER;

    /** Parametr adresy: jen akce, které ještě nezačaly (R76). */
    public const UPCOMING = OfferFilters::UPCOMING_PARAMETER;

    /** Parametr adresy: bez akcí jen z e-shopu (R82). */
    public const WITHOUT_ESHOP = OfferFilters::WITHOUT_ESHOP_PARAMETER;

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.config()->integer('letaky.offers.search_max_length')],
            self::CHAIN => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || self::parseChains($value) === null) {
                    $fail('validation.enum')->translate();
                }
            }],
            self::PRODUCT => ['nullable', 'integer', Rule::exists('products', 'id')],
            self::UPCOMING => ['nullable', 'boolean'],
            self::WITHOUT_ESHOP => ['nullable', 'boolean'],
            ...$this->pageWindowRules(),
        ];
    }

    /**
     * Obchody z hodnoty parametru v pořadí nabídky (stejný výběr = stejná adresa);
     * „vse“ = prázdný seznam (všechny), neznámý obchod = null.
     *
     * @return list<Chain>|null
     */
    public static function parseChains(string $value): ?array
    {
        if ($value === OfferFilters::ALL_CHAINS) {
            return [];
        }

        $values = array_map(trim(...), explode(OfferFilters::CHAIN_SEPARATOR, $value));
        foreach ($values as $part) {
            if (Chain::tryFrom($part) === null) {
                return null;
            }
        }

        return self::inMenuOrder($values);
    }

    /**
     * Obchody z hodnot v pořadí výčtu Chain, bez opakování.
     *
     * @param  array<string>  $values
     * @return list<Chain>
     */
    private static function inMenuOrder(array $values): array
    {
        return array_values(array_filter(Chain::cases(), fn (Chain $chain): bool => in_array($chain->value, $values, true)));
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
     * Vybrané obchody; prázdný seznam = všechny. Bez parametru sledované obchody přihlášeného.
     *
     * @return list<Chain>
     */
    public function chains(): array
    {
        if ($this->filled(self::CHAIN)) {
            return self::parseChains($this->string(self::CHAIN)->toString()) ?? [];
        }

        $user = $this->user();
        if (! $user instanceof User) {
            return [];
        }
        $followed = self::inMenuOrder($user->followedChains()->pluck('chain')->map(fn (Chain $chain): string => $chain->value)->all());

        // Sleduje všechny (nebo žádný) = bez omezení
        return count($followed) === count(Chain::cases()) ? [] : $followed;
    }

    /**
     * Hodnota parametru obchodů pro odkazy stránkování — jen když ji uživatel zvolil
     * (bez parametru zůstanou přihlášenému sledované obchody).
     */
    public function chainParameter(): ?string
    {
        return $this->filled(self::CHAIN) ? (new OfferFilters($this->chains()))->chainParameter() : null;
    }

    /**
     * ID produktu katalogu, jehož akce ukázat, nebo null.
     */
    public function productId(): ?int
    {
        return $this->integer(self::PRODUCT) ?: null;
    }

    /**
     * Jen akce, které ještě nezačaly?
     */
    public function upcomingOnly(): bool
    {
        return $this->boolean(self::UPCOMING);
    }

    /**
     * Bez akcí jen z e-shopu?
     */
    public function withoutEshop(): bool
    {
        return $this->boolean(self::WITHOUT_ESHOP);
    }

    /**
     * Všechny filtry výpisu.
     */
    public function filters(): OfferFilters
    {
        return new OfferFilters($this->chains(), $this->productId(), $this->upcomingOnly(), $this->withoutEshop());
    }
}
