<?php

/**
 * Hledání v aktuálních nabídkách — slova jako začátky slov v názvu, značce a popisu (R71),
 * volitelně jen vybrané obchody, produkt katalogu, budoucí akce, bez e-shopu a podle
 * nastavení Mých obchodů přihlášeného (R100), jen brzy končící, nové a slevy od procent
 * (R101) a oddělení katalogu (OfferFilters, R102).
 *
 * Bez ohledu na diakritiku a velikost písmen („mleko“ najde „Mléko“) díky collation
 * utf8mb4_unicode_ci tabulek. Řazení volí uživatel (OfferListSort, R100); bez volby
 * s hledaným textem podle relevance — název začínající celým textem, všechna slova v názvu,
 * v názvu nebo značce, zbytek (shoda jen v popisu — „pizza“ u Coca-Coly s popisem
 * „MENU PIZZA+COLA“), uvnitř skupiny od nejvyšší slevy; u produktu od nejnižší ceny za
 * jednotku, jinak doporučené (skutečné slevy, nejdřív čerstvé).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Chains\ShoppingPreferencesScope;
use App\Enums\OfferListSort;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Models\Offer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class OfferSearch
{
    /** Sloupce, ve kterých se hledá text. */
    private const SEARCHED_COLUMNS = ['name', 'brand', 'description'];

    /**
     * Cena za jednotku v SQL jako UnitPrice::of (bez ceny cena s kartou); jedna větev CASE na
     * každou PackageUnit — hodnoty doplní unitPriceBindings(). Bez balení NULL.
     */
    private const UNIT_PRICE_SQL = 'COALESCE(price, loyalty_price) * CASE unit WHEN ? THEN ? WHEN ? THEN ? WHEN ? THEN ? END / NULLIF(quantity, 0)';

    /**
     * Sleva v procentech v SQL jako Offer::effectiveDiscountPercent: od obchodu, jinak dopočtená
     * z původní ceny jen u typu „sleva“ (R8); bez slevy NULL. Typ doplní discountBindings().
     */
    private const DISCOUNT_SQL = '(CASE WHEN discount_percent > 0 THEN discount_percent WHEN offer_type = ? AND original_price > price THEN ROUND((1 - price / original_price) * 100) END)';

    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly ShoppingPreferencesScope $preferences,
        private readonly OfferDepartments $departments,
    ) {}

    /**
     * Neskončené a obchodem nestažené (R16) nabídky odpovídající hledání, po stránkách.
     *
     * @return LengthAwarePaginator<int, Offer>
     */
    public function search(?string $text, OfferFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($text, $filters)->paginate($perPage);
    }

    /**
     * Dotaz na neskončené a obchodem nestažené nabídky odpovídající hledání — pro výpis
     * s vlastním stránkováním (Všechny akce načítají víc stránek najednou), seřazený podle
     * zvoleného řazení, nebo podle situace (sortFor).
     *
     * @return Builder<Offer>
     */
    public function query(?string $text, OfferFilters $filters = new OfferFilters): Builder
    {
        $today = $this->calendar->today();
        $query = Offer::query()
            ->active()
            ->notExpired($today)
            ->when($filters->upcomingOnly, fn (Builder $query) => $query->upcoming($today))
            ->when($filters->chains !== [], fn (Builder $query) => $query->whereIn('chain', $filters->chains))
            ->when($filters->withoutEshop, fn (Builder $query) => $query->where('online_only', false))
            ->when($filters->productId, fn (Builder $query, int $productId) => $query->whereHas('productAssignments', fn (Builder $query) => $query->where('product_id', $productId)))
            ->when($filters->preferencesOf, fn (Builder $query, $user) => $this->preferences->apply($query, $user))
            // Končí brzy (R101): už platí a konec je do `ending_soon_days` dní
            ->when($filters->endingSoon, fn (Builder $query) => $query
                ->whereDate('valid_from', '<=', $today->toDateString())
                ->whereDate('valid_to', '<=', $today->addDays(config()->integer('letaky.offers.ending_soon_days'))->toDateString()))
            ->when($filters->freshOnly, fn (Builder $query) => $query->where('created_at', '>=', $this->freshSince()->toDateTimeString()))
            // Oddělení (R102): přes produkt katalogu, ke kterému je akce přiřazená
            ->when($filters->department, fn (Builder $query, string $department) => $query->whereHas(
                'productAssignments',
                fn (Builder $query) => $query->whereHas('product', fn (Builder $query) => $query->whereIn('category_id', $this->departments->categoryIds($department))),
            ))
            ->when($filters->minDiscount, fn (Builder $query, int $percent) => $query->whereRaw(self::DISCOUNT_SQL.' >= ?', [...$this->discountBindings(), $percent]))
            ->with('stores');

        foreach (WordStart::words($text ?? '') as $word) {
            WordStart::where($query, self::SEARCHED_COLUMNS, $word);
        }

        match (self::sortFor($text, $filters)) {
            OfferListSort::Relevance => $this->orderByRelevance($query, (string) $text),
            OfferListSort::Recommended => $this->orderByRecommended($query, $today),
            OfferListSort::Discount => $this->orderByDiscount($query)->tap(fn (Builder $query) => $this->orderByUnitPrice($query)),
            OfferListSort::UnitPrice => $this->orderByUnitPrice($query),
            OfferListSort::EndingSoon => $this->orderByDiscount($query->orderBy('valid_to')),
        };

        return $query->orderBy('name')->orderBy('id');
    }

    /**
     * Řazení výpisu: zvolené, nebo podle situace (OfferListSort::defaultFor). Relevance bez
     * hledaného textu nemá podle čeho řadit — pak také podle situace.
     */
    public static function sortFor(?string $text, OfferFilters $filters): OfferListSort
    {
        $hasText = WordStart::words($text ?? '') !== [];
        $sort = $filters->sort ?? OfferListSort::defaultFor($hasText, $filters->productId !== null);

        return $sort === OfferListSort::Relevance && ! $hasText ? OfferListSort::defaultFor(false, $filters->productId !== null) : $sort;
    }

    /**
     * Doporučené (R100): akce, které platí dnes, před těmi, které teprve začnou (R76); uvnitř
     * nejdřív skutečné slevy — zveřejněné za posledních `letaky.offers.fresh_days`
     * dní, pak starší, obojí od nejvyšší slevy; zbytek (akční ceny, akce s kartou, na více kusů)
     * od nejnovějších. Bez textu tak výpis neotevírá nejstarší akce e-shopu, ale to
     * nejzajímavější, co se dá koupit hned.
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    private function orderByRecommended(Builder $query, CarbonImmutable $today): Builder
    {
        return $query->orderByRaw('valid_from > ?', [$today->toDateString()])
            ->orderByRaw(self::DISCOUNT_SQL.' IS NULL', $this->discountBindings())
            ->orderByRaw('created_at >= ? DESC', [$this->freshSince()->toDateTimeString()])
            ->orderByRaw(self::DISCOUNT_SQL.' DESC', $this->discountBindings())
            ->orderByDesc('created_at');
    }

    /**
     * Od kdy je akce čerstvá (zveřejněná za posledních `letaky.offers.fresh_days` dní) — řazení
     * Doporučené a filtr Nové (R100, R101). Čas zveřejnění je v UTC.
     */
    public function freshSince(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(config()->integer('letaky.offers.fresh_days'));
    }

    /**
     * Od nejvyšší slevy, akce bez známé slevy na konec.
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    private function orderByDiscount(Builder $query): Builder
    {
        return $query->orderByRaw(self::DISCOUNT_SQL.' IS NULL', $this->discountBindings())
            ->orderByRaw(self::DISCOUNT_SQL.' DESC', $this->discountBindings());
    }

    /**
     * Od nejnižší ceny za kilo, litr nebo kus („kde je nejlevněji“, R94), akce bez balení na konec.
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    private function orderByUnitPrice(Builder $query): Builder
    {
        $bindings = $this->unitPriceBindings();

        return $query->orderByRaw(self::UNIT_PRICE_SQL.' IS NULL', $bindings)->orderByRaw(self::UNIT_PRICE_SQL, $bindings);
    }

    /**
     * Podle relevance k hledanému textu, uvnitř skupiny od nejvyšší slevy (R71).
     *
     * @param  Builder<Offer>  $query
     * @return Builder<Offer>
     */
    private function orderByRelevance(Builder $query, string $text): Builder
    {
        [$relevance, $bindings] = $this->relevance($text);

        return $query->orderByRaw($relevance, $bindings)->orderByDesc('discount_percent');
    }

    /**
     * Hodnoty pro DISCOUNT_SQL: typ „sleva“.
     *
     * @return list<string>
     */
    private function discountBindings(): array
    {
        return [OfferType::Discount->value];
    }

    /**
     * Hodnoty pro UNIT_PRICE_SQL: jednotka a její základ (g a ml × 1000, ks × 1).
     *
     * @return list<int|string>
     */
    private function unitPriceBindings(): array
    {
        return array_merge(...array_map(fn (PackageUnit $unit): array => [$unit->value, $unit->unitPriceBase()], PackageUnit::cases()));
    }

    /**
     * Pořadí podle relevance jako SQL CASE: 0 = název začíná celým textem, 1 = všechna slova
     * začínají slova názvu, 2 = slova jsou v názvu nebo značce, 3 = shoda i v popisu.
     *
     * @return array{literal-string, list<string>}
     */
    private function relevance(string $text): array
    {
        $inName = '';
        $inNameOrBrand = '';
        $nameBindings = [];
        $nameOrBrandBindings = [];
        foreach (WordStart::words($text) as $index => $word) {
            [$name, $bindings] = WordStart::sql('name', $word);
            [$brand, $brandBindings] = WordStart::sql('brand', $word);
            $and = $index === 0 ? '' : ' AND ';
            $inName .= $and.$name;
            $inNameOrBrand .= $and.'('.$name.' OR '.$brand.')';
            $nameBindings = [...$nameBindings, ...$bindings];
            $nameOrBrandBindings = [...$nameOrBrandBindings, ...$bindings, ...$brandBindings];
        }

        return [
            'CASE WHEN name LIKE ? THEN 0 WHEN '.$inName.' THEN 1 WHEN '.$inNameOrBrand.' THEN 2 ELSE 3 END',
            [addcslashes(trim($text), '%_\\').'%', ...$nameBindings, ...$nameOrBrandBindings],
        ];
    }
}
