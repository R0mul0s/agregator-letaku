<?php

/**
 * Prodejna, ve které akce platí (R49). Akce bez řádků platí ve všech prodejnách obchodu;
 * řádky má jen akce, která není ve všech (pultové maso Kauflandu).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $offer_id
 * @property string $store_code
 */
class OfferStore extends Model
{
    /** Složený primární klíč (offer_id, store_code) — Eloquent ho neumí, záznamy se jen vkládají a mažou. */
    protected $primaryKey = 'offer_id';

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'offer_id',
        'store_code',
    ];

    /**
     * Akce.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
