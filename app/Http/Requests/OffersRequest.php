<?php

/**
 * Parametry přehledu nabídek — hledaný text, obchody, produkt katalogu (R71), jen budoucí
 * akce (R76), bez akcí jen z e-shopu (R82) a načtené stránky (R43).
 *
 * Obchody jsou v parametru `chain` oddělené čárkou (`?chain=kaufland,lidl`); jeden obchod
 * a produkt katalogu mají i čistou adresu `/akce/lidl`, `/akce/pivo` (R94, OfferPages). Bez
 * parametru vidí přihlášený své sledované obchody, nepřihlášený všechny; `?chain=vse` jsou
 * vždy všechny.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Offers\OfferFilters;
use App\Domain\Offers\OfferPages;
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
     * Obchod nebo produkt z části adresy (`/akce/lidl`, `/akce/pivo`, R94); bez ní oba null.
     * Neznámou část adresy hlásí kontroler jako 404.
     *
     * @return array{chain: Chain|null, productId: int|null}|null null = část adresy neexistuje
     */
    public function pathTarget(): ?array
    {
        $slug = $this->route('slug');

        return is_string($slug) ? app(OfferPages::class)->resolve($slug) : ['chain' => null, 'productId' => null];
    }

    /**
     * Vybrané obchody; prázdný seznam = všechny. Parametr má přednost před obchodem v adrese
     * (`/akce/lidl`); bez obou sledované obchody přihlášeného.
     *
     * @return list<Chain>
     */
    public function chains(): array
    {
        $explicit = $this->explicitChains();
        if ($explicit !== null) {
            return $explicit;
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
     * Obchody, které uživatel zvolil parametrem nebo adresou (`/akce/lidl`); null = nezvolil
     * (přihlášenému pak zůstanou sledované obchody).
     *
     * @return list<Chain>|null
     */
    private function explicitChains(): ?array
    {
        if ($this->filled(self::CHAIN)) {
            return self::parseChains($this->string(self::CHAIN)->toString()) ?? [];
        }
        $pathChain = $this->pathTarget()['chain'] ?? null;

        return $pathChain === null ? null : [$pathChain];
    }

    /**
     * ID produktu katalogu, jehož akce ukázat (parametr, nebo `/akce/pivo`), nebo null.
     */
    public function productId(): ?int
    {
        return ($this->integer(self::PRODUCT) ?: null) ?? $this->pathTarget()['productId'] ?? null;
    }

    /**
     * Filtry, které uživatel zvolil — pro odkazy stránkování a čistou adresu (R94). Na rozdíl
     * od filters() bez sledovaných obchodů přihlášeného, ty v adrese nebývají.
     */
    public function chosenFilters(): OfferFilters
    {
        return new OfferFilters($this->explicitChains() ?? [], $this->productId(), $this->upcomingOnly(), $this->withoutEshop());
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
