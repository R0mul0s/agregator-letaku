<?php

/**
 * Ruční oprava „nabídka k produktu nepatří“ (R30) — automatické přiřazení ji přeskočí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $offer_id
 * @property int $product_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Offer $offer
 */
class OfferProductExclusion extends Model
{
    /** Složený klíč (offer_id, product_id) — model se zapisuje jen dotazem. */
    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'offer_id',
        'product_id',
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
     * Vyřazená nabídka.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
