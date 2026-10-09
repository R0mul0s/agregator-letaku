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
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Převod podílu na procenta slevy. */
    private const PERCENT = 100;

    /**
     * Cena, za kterou akci zná každý (bez ohledu na karty uživatele): bez karty, u akce jen
     * s kartou cena s kartou — „cena od“ a cena za jednotku ve výpisech (R113).
     */
    public const PUBLIC_PRICE_SQL = 'COALESCE(offers.price, offers.loyalty_price)';

    /**
     * Sleva v procentech v SQL jako effectiveDiscountPercent(): od obchodu, jinak dopočtená
     * z původní ceny jen u typu „sleva“ (R8); bez slevy NULL. Typ doplní discountSql().
     */
    private const DISCOUNT_SQL = '(CASE WHEN discount_percent > 0 THEN discount_percent WHEN offer_type = ? AND original_price > price THEN ROUND((1 - price / original_price) * 100) END)';

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
     * Přiřazení nabídky k produktům katalogu (R30).
     *
     * @return HasMany<OfferProduct, $this>
     */
    public function productAssignments(): HasMany
    {
        return $this->hasMany(OfferProduct::class);
    }

    /**
     * Prodejny, ve kterých nabídka platí (R49); žádná = všechny prodejny obchodu.
     *
     * @return HasMany<OfferStore, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(OfferStore::class);
    }

    /**
     * Nabídky, které platí aspoň v jedné z prodejen (R49): bez omezení na prodejny, nebo
     * s některou z nich.
     *
     * @param  Builder<self>  $query
     * @param  list<string>  $storeCodes
     */
    public function scopeAvailableInStores(Builder $query, array $storeCodes): void
    {
        $query->where(fn (Builder $available) => $available
            ->whereDoesntHave('stores')
            ->orWhereHas('stores', fn (Builder $stores) => $stores->whereIn('store_code', $storeCodes)));
    }

    /**
     * Všechny sloupce kromě surové odpovědi obchodu (`raw`, u Billy a Globusu ~2 kB na řádek) —
     * výpisy a párování ji nepotřebují, slouží jen k ladění zdroje (R106). S `Model::shouldBeStrict`
     * hodí přístup k `raw` u takto načtené nabídky výjimku, takže se na ni nedá omylem spolehnout.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithoutRaw(Builder $query): void
    {
        $table = $this->getTable();
        $columns = array_values(array_diff([$this->getKeyName(), ...$this->fillable, self::CREATED_AT, self::UPDATED_AT], ['raw']));

        $query->select(array_map(fn (string $column): string => $table.'.'.$column, $columns));
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
        $query->where('valid_to', '>=', $localToday->toDateString());
    }

    /**
     * Nabídky, které ke dni ještě nezačaly — leták na příští dny (R76).
     *
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query, CarbonImmutable $localToday): void
    {
        $query->where('valid_from', '>', $localToday->toDateString());
    }

    /**
     * Začíná nabídka až po daném dni (R76)? Platnost i den jsou místní data.
     */
    public function isUpcoming(CarbonImmutable $localToday): bool
    {
        return $this->valid_from->greaterThan($localToday);
    }

    /**
     * Sleva v procentech: od obchodu, jinak dopočtená z původní ceny; jen u typu „sleva“ (R8).
     * Jediný výpočet slevy — Vue ji dostává hotovou v OfferPresenter, SQL ji počítá stejně
     * (discountSql, R113).
     */
    public function effectiveDiscountPercent(): ?int
    {
        if ($this->discount_percent !== null && $this->discount_percent > 0) {
            return $this->discount_percent;
        }

        if ($this->offer_type !== OfferType::Discount || $this->price === null || $this->original_price === null || $this->original_price <= $this->price) {
            return null;
        }

        return (int) round((1 - $this->price / $this->original_price) * self::PERCENT);
    }

    /**
     * Sleva v procentech jako výraz SQL s hodnotami — pro filtr a řazení podle slevy, ať se
     * nerozejde s effectiveDiscountPercent() (sloupec `discount_percent` sám chybí u slev
     * dopočtených z přeškrtnuté ceny, R113).
     *
     * @return array{literal-string, list<string>}
     */
    public static function discountSql(): array
    {
        return [self::DISCOUNT_SQL, [OfferType::Discount->value]];
    }

    /**
     * Od nejvyšší slevy (effectiveDiscountPercent), akce bez slevy na konec.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrderByDiscount(Builder $query): void
    {
        [$sql, $bindings] = self::discountSql();
        $query->orderByRaw($sql.' IS NULL', $bindings)->orderByRaw($sql.' DESC', $bindings);
    }

    /**
     * Akce se slevou aspoň tolik procent (effectiveDiscountPercent).
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithDiscountOf(Builder $query, int $minPercent): void
    {
        [$sql, $bindings] = self::discountSql();
        $query->whereRaw($sql.' >= ?', [...$bindings, $minPercent]);
    }
}
