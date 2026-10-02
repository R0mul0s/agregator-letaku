<?php

/**
 * Našeptávač hledání ve Všech akcích — produkty katalogu a názvy aktuálních akcí, ve kterých
 * text začíná některé slovo (bez ohledu na diakritiku a velikost písmen, porovnání MariaDB);
 * názvy, které textem začínají, jsou první.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

final class SearchSuggestions
{
    /** Vzory začátku slova v LIKE: začátek názvu, po mezeře, po pomlčce a lomítku („Coca-Cola“, „Fanta/Sprite“). */
    private const WORD_STARTS = ['', '% ', '%-', '%/'];

    public function __construct(private readonly LocalCalendar $calendar) {}

    /**
     * Návrhy pro text: nejdřív produkty katalogu, pak názvy neskončených akcí (bez opakování).
     *
     * @return list<array{type: 'product'|'offer', label: string}>
     */
    public function for(string $text, ?Chain $chain): array
    {
        $limit = config()->integer('letaky.offers.suggest_limit');
        $escaped = addcslashes($text, '%_\\');

        $products = Product::query()
            ->tap(fn (Builder $query) => $this->whereWordStarts($query, $escaped))
            ->orderByRaw('name LIKE ? DESC', [$escaped.'%'])
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->all();

        $offers = Offer::query()
            ->active()
            ->notExpired($this->calendar->today())
            ->when($chain, fn (Builder $query, Chain $chain) => $query->where('chain', $chain))
            ->tap(fn (Builder $query) => $this->whereWordStarts($query, $escaped))
            ->groupBy('name')
            ->orderByRaw('name LIKE ? DESC', [$escaped.'%'])
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->all();

        $suggestions = [];
        foreach ($products as $name) {
            $suggestions[mb_strtolower($name)] = ['type' => 'product', 'label' => $name];
        }
        foreach ($offers as $name) {
            $suggestions[mb_strtolower($name)] ??= ['type' => 'offer', 'label' => $name];
        }

        return array_slice(array_values($suggestions), 0, $limit);
    }

    /**
     * Název obsahuje text jako začátek slova („cola“ najde „Coca-Cola“, ne „Chocolate“).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function whereWordStarts(Builder $query, string $escaped): void
    {
        $query->where(function (Builder $query) use ($escaped): void {
            foreach (self::WORD_STARTS as $prefix) {
                $query->orWhere('name', 'like', $prefix.$escaped.'%');
            }
        });
    }
}
