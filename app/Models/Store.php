<?php

/**
 * Prodejna obchodu (zatím jen Kaufland, R49) se seznamem akcí, které v ní platí.
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
 * @property list<string>|null $offer_keys
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
     * Kdy se naposledy stáhl seznam akcí některé prodejny obchodu (UTC) — konec posledního
     * úspěšného stažení prodejen pro /health/tasks (R115); null = nikdy.
     */
    public static function lastListFetchedAt(Chain $chain): ?CarbonImmutable
    {
        $fetchedAt = self::query()->where('chain', $chain)->max('offer_keys_fetched_at');

        // Databáze ukládá čas v UTC (config/app.php)
        return is_string($fetchedAt) ? CarbonImmutable::parse($fetchedAt, 'UTC') : null;
    }
}
