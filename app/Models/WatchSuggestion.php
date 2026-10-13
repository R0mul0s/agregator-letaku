<?php

/**
 * Návrh, co hlídat (R148) — položka, kterou si uživatel opakovaně dává do nákupního seznamu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-13
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $term Název po TextNormalizer — klíč návrhu
 * @property string $name Název k zobrazení
 * @property int|null $product_id Produkt katalogu; null = vlastní slova
 * @property int $hits
 * @property CarbonImmutable $last_hit_at
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Product|null $product
 */
class WatchSuggestion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'term',
        'name',
        'product_id',
        'hits',
        'last_hit_at',
        'dismissed_at',
    ];

    /**
     * Výchozí hodnoty sloupců — přísný režim modelů jinak u nově založeného záznamu hlásí
     * chybějící atribut.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'product_id' => null,
        'hits' => 1,
        'dismissed_at' => null,
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_hit_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Vlastník návrhu.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Produkt katalogu, který návrh nabízí; null = vlastní slova.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
