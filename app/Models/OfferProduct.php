<?php

/**
 * Přiřazení nabídky k produktu katalogu (R30) — automatické podle pravidel produktu, nebo ruční.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $offer_id
 * @property int $product_id
 * @property MatchStatus $status
 * @property bool $is_manual
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Offer $offer
 * @property-read Product $product
 */
class OfferProduct extends Model
{
    /** @var string */
    protected $table = 'offer_product';

    /** Složený klíč (offer_id, product_id) — model se zapisuje jen hromadně nebo dotazem. */
    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'offer_id',
        'product_id',
        'status',
        'is_manual',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'is_manual' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Přiřazená nabídka.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Produkt katalogu.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Připojí akce (`offers`) a nechá jen aktuální: obchod je nestáhl (R16) a neskončily —
     * stejně jako Offer::active()->notExpired(); `$startedOnly` vynechá i akce, které ještě
     * nezačaly (R76). Jedno místo pro výpisy, které jdou přes přiřazení k produktům (R113).
     *
     * @param  Builder<self>  $query
     */
    public function scopeJoinCurrentOffers(Builder $query, CarbonImmutable $localToday, bool $startedOnly = false): void
    {
        $today = $localToday->toDateString();
        $query->join('offers', 'offers.id', '=', 'offer_product.offer_id')
            ->whereNull('offers.withdrawn_at')
            ->where('offers.valid_to', '>=', $today)
            ->when($startedOnly, fn (Builder $query) => $query->where('offers.valid_from', '<=', $today));
    }
}
