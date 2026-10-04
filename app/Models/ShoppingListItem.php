<?php

/**
 * Položka nákupního seznamu (R61) — akce, kterou si uživatel dal do seznamu; v obchodě ji odškrtne.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $offer_id
 * @property CarbonImmutable|null $checked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Offer $offer
 * @property-read User $user
 */
class ShoppingListItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'offer_id',
        'checked_at',
    ];

    /**
     * Výchozí hodnoty sloupců — přísný režim modelů jinak u nově založeného záznamu hlásí
     * chybějící atribut.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'checked_at' => null,
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Akce v seznamu.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Uživatel, jehož je seznam.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
