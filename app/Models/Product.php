<?php

/**
 * Produkt katalogu — věc, kterou člověk hledá bez ohledu na obchod („Polotučné mléko“),
 * s pravidly párování jako hlídaná položka (R18, R29).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property string $keywords
 * @property string|null $variant_keywords
 * @property string|null $exclude_keywords
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'name',
        'keywords',
        'variant_keywords',
        'exclude_keywords',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Kategorie stromu (R28); produkt nemusí být zařazený.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Přiřazení nabídek k produktu (R30).
     *
     * @return HasMany<OfferProduct, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(OfferProduct::class);
    }

    /**
     * Nabídky, které podle admina k produktu nepatří (R30).
     *
     * @return HasMany<OfferProductExclusion, $this>
     */
    public function exclusions(): HasMany
    {
        return $this->hasMany(OfferProductExclusion::class);
    }
}
