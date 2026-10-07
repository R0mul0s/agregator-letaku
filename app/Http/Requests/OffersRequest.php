<?php

/**
 * Parametry přehledu nabídek — hledaný text, obchody, produkt katalogu (R71), jen budoucí
 * akce (R76), bez akcí jen z e-shopu (R82), řazení, nastavení Mých obchodů (R100), končí brzy,
 * nové, slevy od procent (R101) a načtené stránky (R43).
 *
 * Přihlášenému výpis uplatní nastavení Mých obchodů (prodejny, karty, e-shop) — `?moje-obchody=0`
 * ho vypne.
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
use App\Enums\OfferListSort;
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

    /** Parametr adresy: jen akce, které brzy končí (R101). */
    public const ENDING_SOON = OfferFilters::ENDING_SOON_PARAMETER;

    /** Parametr adresy: jen nově zveřejněné akce (R101). */
    public const FRESH = OfferFilters::FRESH_PARAMETER;

    /** Parametr adresy: jen skutečné slevy od procent (R101). */
    public const MIN_DISCOUNT = OfferFilters::MIN_DISCOUNT_PARAMETER;

    /** Parametr adresy: řazení (R100). */
    public const SORT = OfferFilters::SORT_PARAMETER;

    /** Parametr adresy: `0` = bez nastavení Mých obchodů přihlášeného (R100). */
    public const SHOPPING_PREFERENCES = OfferFilters::SHOPPING_PREFERENCES_PARAMETER;

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
            self::ENDING_SOON => ['nullable', 'boolean'],
            self::FRESH => ['nullable', 'boolean'],
            self::MIN_DISCOUNT => ['nullable', 'integer', Rule::in(config()->array('letaky.account.min_discount_options'))],
            self::SORT => ['nullable', Rule::enum(OfferListSort::class)],
            self::SHOPPING_PREFERENCES => ['nullable', 'boolean'],
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
     * Zvolil uživatel výslovně všechny obchody (`?chain=vse`)? Odkazy to musí zachovat —
     * přihlášený by bez parametru dostal své sledované.
     */
    public function allChainsChosen(): bool
    {
        return $this->string(self::CHAIN)->toString() === OfferFilters::ALL_CHAINS;
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
     * od filters() bez sledovaných obchodů a nastavení Mých obchodů přihlášeného, ty v adrese
     * nebývají (jen jejich vypnutí).
     */
    public function chosenFilters(): OfferFilters
    {
        return new OfferFilters(
            $this->explicitChains() ?? [],
            $this->productId(),
            $this->upcomingOnly(),
            $this->withoutEshop(),
            $this->sort(),
            preferencesOff: $this->preferencesOff(),
            endingSoon: $this->boolean(self::ENDING_SOON),
            freshOnly: $this->boolean(self::FRESH),
            minDiscount: $this->minDiscount(),
        );
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
     * Jen skutečné slevy od tolika procent (R101), nebo null = všechny akce.
     */
    public function minDiscount(): ?int
    {
        return $this->integer(self::MIN_DISCOUNT) ?: null;
    }

    /**
     * Zvolené řazení (R100), nebo null = podle situace.
     */
    public function sort(): ?OfferListSort
    {
        return $this->enum(self::SORT, OfferListSort::class);
    }

    /**
     * Vypnul přihlášený nastavení Mých obchodů (`?moje-obchody=0`, R100)? Nepřihlášený žádné nemá.
     */
    public function preferencesOff(): bool
    {
        return $this->user() instanceof User && $this->filled(self::SHOPPING_PREFERENCES) && ! $this->boolean(self::SHOPPING_PREFERENCES);
    }

    /**
     * Uživatel, jehož nastavení Mých obchodů (prodejny, karty, e-shop) výpis uplatní — přihlášený,
     * pokud ho nevypnul (R100).
     */
    public function preferencesOf(): ?User
    {
        $user = $this->user();

        return $user instanceof User && ! $this->preferencesOff() ? $user : null;
    }

    /**
     * Všechny filtry výpisu.
     */
    public function filters(): OfferFilters
    {
        return new OfferFilters(
            $this->chains(),
            $this->productId(),
            $this->upcomingOnly(),
            $this->withoutEshop(),
            $this->sort(),
            $this->preferencesOf(),
            $this->preferencesOff(),
            $this->boolean(self::ENDING_SOON),
            $this->boolean(self::FRESH),
            $this->minDiscount(),
        );
    }
}
