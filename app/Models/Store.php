<?php

/**
 * Prodejna obchodu (Kaufland R49, COOP R169) se seznamem akcí, které v ní platí.
 * Seznam stahuje ImportStores; import nabídek z něj zjistí, v kterých prodejnách akce platí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property Chain $chain
 * @property string $code
 * @property string $name
 * @property string $city
 * @property list<string>|null $offer_keys Akce platné v prodejně („id|od|do“), u COOP zkratky letáků („SČ“, R169)
 * @property CarbonImmutable|null $offer_keys_fetched_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Store extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'chain',
        'code',
        'name',
        'city',
        'offer_keys',
        'offer_keys_fetched_at',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chain' => Chain::class,
            'offer_keys' => 'array',
            'offer_keys_fetched_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Od kdy je čerstvý seznam akcí aspoň u `store_lists_min_share` prodejen obchodu (UTC) — pro
     * /health/tasks (R115). Nejnovější seznam by výpadek neukázal: COOP obnovuje prodejny po dávkách
     * a prodejny, které se trvale nestahují, by tiše vypadly (R170). Null = tolik seznamů není.
     */
    public static function lastListFetchedAt(Chain $chain): ?CarbonImmutable
    {
        $needed = (int) ceil(self::query()->where('chain', $chain)->count() * config()->float("letaky.sources.{$chain->value}.store_lists_min_share"));
        $fetchedAt = self::query()
            ->where('chain', $chain)
            ->whereNotNull('offer_keys_fetched_at')
            ->orderByDesc('offer_keys_fetched_at')
            ->offset(max(0, $needed - 1))
            ->value('offer_keys_fetched_at');

        // value() modelu vrací přetypovaný sloupec
        return $fetchedAt instanceof CarbonImmutable ? $fetchedAt : null;
    }
}
