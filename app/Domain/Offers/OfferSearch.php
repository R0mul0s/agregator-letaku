<?php

/**
 * Hledání v aktuálních nabídkách — text v názvu, značce a popisu, volitelně jeden obchod.
 *
 * Bez ohledu na diakritiku a velikost písmen („mleko“ najde „Mléko“) díky collation
 * utf8mb4_unicode_ci tabulek.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class OfferSearch
{
    /** Sloupce, ve kterých se hledá text. */
    private const SEARCHED_COLUMNS = ['name', 'brand', 'description'];

    public function __construct(private readonly LocalCalendar $calendar) {}

    /**
     * Neskončené a obchodem nestažené (R16) nabídky odpovídající hledání, od nejdříve platných, po stránkách.
     *
     * @return LengthAwarePaginator<int, Offer>
     */
    public function search(?string $text, ?Chain $chain, int $perPage): LengthAwarePaginator
    {
        return $this->query($text, $chain)->paginate($perPage);
    }

    /**
     * Dotaz na neskončené a obchodem nestažené nabídky odpovídající hledání, seřazený od nejdříve
     * platných — pro výpis s vlastním stránkováním (Všechny akce načítají víc stránek najednou).
     *
     * @return Builder<Offer>
     */
    public function query(?string $text, ?Chain $chain): Builder
    {
        return Offer::query()
            ->active()
            ->notExpired($this->calendar->today())
            ->when($chain, fn (Builder $query, Chain $chain) => $query->where('chain', $chain))
            ->when($text, fn (Builder $query, string $text) => $this->whereText($query, $text))
            ->with('stores')
            ->orderBy('valid_from')
            ->orderBy('chain')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * Každé slovo hledání musí být v některém ze sloupců („mléko polotučné“).
     *
     * @param  Builder<Offer>  $query
     */
    private function whereText(Builder $query, string $text): void
    {
        foreach (preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $pattern = '%'.addcslashes($word, '%_\\').'%';
            $query->where(function (Builder $query) use ($pattern): void {
                foreach (self::SEARCHED_COLUMNS as $column) {
                    $query->orWhere($column, 'like', $pattern);
                }
            });
        }
    }
}
