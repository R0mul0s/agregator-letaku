<?php

/**
 * Prodejna obchodu. Uživatel si vybírá konkrétní prodejny (R3).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use App\Enums\StoreFormat;
use Carbon\CarbonImmutable;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property Chain $chain
 * @property string $external_id
 * @property string $name
 * @property StoreFormat|null $format
 * @property string|null $city
 * @property string|null $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'chain',
        'external_id',
        'name',
        'format',
        'city',
        'address',
        'latitude',
        'longitude',
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
            'format' => StoreFormat::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Uživatelé, kteří mají prodejnu vybranou.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
