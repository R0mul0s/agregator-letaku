<?php

/**
 * „Tohle ne“ (R125): akce, kterou uživatel skryl u své hlídané položky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $watch_item_id
 * @property int $offer_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Offer $offer
 * @property-read WatchItem $watchItem
 */
class WatchItemOfferExclusion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'watch_item_id',
        'offer_id',
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
     * Skrytá akce.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Hlídaná položka, u které je akce skrytá.
     *
     * @return BelongsTo<WatchItem, $this>
     */
    public function watchItem(): BelongsTo
    {
        return $this->belongsTo(WatchItem::class);
    }
}
