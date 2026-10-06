<?php

/**
 * Hledání v aktuálních nabídkách — slova jako začátky slov v názvu, značce a popisu (R71),
 * volitelně jen vybrané obchody, produkt katalogu, budoucí akce a bez e-shopu (OfferFilters).
 *
 * Bez ohledu na diakritiku a velikost písmen („mleko“ najde „Mléko“) díky collation
 * utf8mb4_unicode_ci tabulek. S hledaným textem řadí podle relevance: název začínající
 * celým textem, všechna slova v názvu, v názvu nebo značce, zbytek (shoda jen v popisu —
 * „pizza“ u Coca-Coly s popisem „MENU PIZZA+COLA“); uvnitř skupiny od nejvyšší slevy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\PackageUnit;
use App\Models\Offer;
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

    public function __construct(private readonly LocalCalendar $calendar) {}

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
     * s vlastním stránkováním (Všechny akce načítají víc stránek najednou). Bez textu od
     * nejdříve platných, s textem podle relevance.
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
            ->with('stores');

        if ($text === null || WordStart::words($text) === []) {
            // Akce produktu (R94, „kde je nejlevněji“): od nejnižší ceny za kilo, litr nebo kus,
            // akce bez balení na konec
            if ($filters->productId !== null) {
                $bindings = $this->unitPriceBindings();
                $query->orderByRaw(self::UNIT_PRICE_SQL.' IS NULL', $bindings)->orderByRaw(self::UNIT_PRICE_SQL, $bindings);
            }

            return $query->orderBy('valid_from')->orderBy('chain')->orderBy('name')->orderBy('id');
        }

        foreach (WordStart::words($text) as $word) {
            WordStart::where($query, self::SEARCHED_COLUMNS, $word);
        }
        [$relevance, $bindings] = $this->relevance($text);

        return $query->orderByRaw($relevance, $bindings)
            ->orderByDesc('discount_percent')
            ->orderBy('name')
            ->orderBy('id');
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
