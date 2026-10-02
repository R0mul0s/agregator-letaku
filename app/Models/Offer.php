<?php

/**
 * Akční nabídka obchodu v jednotném tvaru. Ceny v haléřích, platnost jako místní datum (R7).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Enums\StoreFormat;
use Carbon\CarbonImmutable;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property Chain $chain
 * @property int $leaflet_id
 * @property int $scrape_run_id
 * @property CarbonImmutable|null $withdrawn_at
 * @property StoreFormat|null $store_format
 * @property string $external_id
 * @property string $name
 * @property string|null $brand
 * @property string|null $description
 * @property string|null $variant_note
 * @property string|null $package_text
 * @property float|null $quantity
 * @property PackageUnit|null $unit
 * @property int|null $price
 * @property int|null $original_price
 * @property int|null $loyalty_price
 * @property LoyaltyProgram|null $loyalty_program
 * @property int|null $discount_percent
 * @property OfferType $offer_type
 * @property string|null $promotion_text
 * @property bool $online_only
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable $valid_to
 * @property string|null $source_category
 * @property string|null $image_url
 * @property string|null $source_url
 * @property array<string, mixed> $raw
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'chain',
        'leaflet_id',
        'scrape_run_id',
        'withdrawn_at',
        'store_format',
        'external_id',
        'name',
        'brand',
        'description',
        'variant_note',
        'package_text',
        'quantity',
        'unit',
        'price',
        'original_price',
        'loyalty_price',
        'loyalty_program',
        'discount_percent',
        'offer_type',
        'promotion_text',
        'online_only',
        'valid_from',
        'valid_to',
        'source_category',
        'image_url',
        'source_url',
        'raw',
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
            'store_format' => StoreFormat::class,
            'quantity' => 'float',
            'unit' => PackageUnit::class,
            'price' => 'integer',
            'original_price' => 'integer',
            'loyalty_price' => 'integer',
            'loyalty_program' => LoyaltyProgram::class,
            'discount_percent' => 'integer',
            'offer_type' => OfferType::class,
            'online_only' => 'boolean',
            'withdrawn_at' => 'immutable_datetime',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'raw' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Zdroj nabídky (leták, akční stránka, e-shop).
     *
     * @return BelongsTo<Leaflet, $this>
     */
    public function leaflet(): BelongsTo
    {
        return $this->belongsTo(Leaflet::class);
    }

    /**
     * Nabídky, které obchod nestáhl před koncem platnosti (R16).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('withdrawn_at');
    }

    /**
     * Nabídky, které ke dni ještě neskončily — platné i budoucí (leták na příští týden).
     *
     * @param  Builder<self>  $query
     */
    public function scopeNotExpired(Builder $query, CarbonImmutable $localToday): void
    {
        $query->whereDate('valid_to', '>=', $localToday->toDateString());
    }
}
