<?php

/**
 * Kdy se naposledy změnil obsah veřejných stránek akcí (R122) — `lastmod` v sitemap.xml.
 *
 * Změna = akce přibyla (`created_at`), nebo ji obchod stáhl (`withdrawn_at`) — stejně jako
 * u ohlášení přes IndexNow (ChangedOfferPages, R105). Stránka obchodu bere akce obchodu,
 * stránka produktu akce přiřazené k produktu, aktuální týden Nejlepších slev akce se slevou
 * platné v tom týdnu (R128), úvodní stránka a Všechny akce nejpozdější změnu vůbec. `updated_at` se nepoužívá — hromadný zápis ho přepíše při každém stažení i u akce,
 * která se nezměnila; čas stažení taky ne — posouval by datum všech stránek ~14× denně a Google
 * by mu přestal věřit. Začátek a konec platnosti beze stažení se nepočítá — stažení dvakrát
 * denně ho téměř vždy zachytí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\OfferType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class OfferPageChanges
{
    /** Nejpozdější přibytí nebo stažení akce ve skupině (UTC); bez stažené akce jen přibytí. */
    private const LAST_CHANGE_SQL = 'GREATEST(MAX(offers.created_at), COALESCE(MAX(offers.withdrawn_at), MAX(offers.created_at))) AS changed_at';

    /**
     * Poslední změna akcí po obchodech.
     *
     * @return array<string, CarbonImmutable> Hodnota App\Enums\Chain => čas změny
     */
    public function byChain(): array
    {
        $rows = DB::table('offers')
            ->selectRaw('offers.chain, '.self::LAST_CHANGE_SQL)
            ->groupBy('offers.chain')
            ->pluck('changed_at', 'chain');

        return $this->parse($rows->all());
    }

    /**
     * Poslední změna akcí přiřazených k produktům katalogu.
     *
     * @param  list<int>  $productIds
     * @return array<int, CarbonImmutable> ID produktu => čas změny
     */
    public function byProduct(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = DB::table('offer_product')
            ->join('offers', 'offers.id', '=', 'offer_product.offer_id')
            ->whereIn('offer_product.product_id', $productIds)
            ->selectRaw('offer_product.product_id, '.self::LAST_CHANGE_SQL)
            ->groupBy('offer_product.product_id')
            ->pluck('changed_at', 'product_id');

        return $this->parse($rows->all());
    }

    /**
     * Poslední změna akcí se slevou, které platí aspoň jeden den období — stránka Nejlepší slevy
     * týdne (R128). Leták na příští týden aktuální týden nezmění.
     */
    public function byPeriod(CarbonImmutable $localFrom, CarbonImmutable $localTo): ?CarbonImmutable
    {
        $changedAt = DB::table('offers')
            ->where('offers.offer_type', OfferType::Discount->value)
            ->where('offers.valid_from', '<=', $localTo->toDateString())
            ->where('offers.valid_to', '>=', $localFrom->toDateString())
            ->selectRaw(self::LAST_CHANGE_SQL)
            ->value('changed_at');

        return $this->parse(['period' => $changedAt])['period'] ?? null;
    }

    /**
     * Časy z databáze (UTC) jako CarbonImmutable; prázdné vynechá.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $rows
     * @return array<TKey, CarbonImmutable>
     */
    private function parse(array $rows): array
    {
        $changes = [];
        foreach ($rows as $key => $value) {
            if (is_string($value)) {
                // Databáze ukládá čas v UTC (config/app.php)
                $changes[$key] = CarbonImmutable::parse($value, 'UTC');
            }
        }

        return $changes;
    }
}
