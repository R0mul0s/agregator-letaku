<?php

/**
 * Položka nákupního seznamu (R61) — akce, kterou si uživatel dal do seznamu, nebo vlastní
 * položka bez akce s názvem a nepovinně obchodem (R130); v obchodě ji odškrtne.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $offer_id
 * @property string|null $custom_name
 * @property Chain|null $chain
 * @property CarbonImmutable|null $checked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Offer|null $offer
 * @property-read User $user
 */
class ShoppingListItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'offer_id',
        'custom_name',
        'chain',
        'checked_at',
    ];

    /**
     * Výchozí hodnoty sloupců — přísný režim modelů jinak u nově založeného záznamu hlásí
     * chybějící atribut.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'offer_id' => null,
        'custom_name' => null,
        'chain' => null,
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
            'chain' => Chain::class,
            'checked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Akce v seznamu; vlastní položka (R130) ji nemá.
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

    /**
     * Obchod položky: obchod akce, u vlastní položky zvolený (null = kdekoli).
     */
    public function storeChain(): ?Chain
    {
        return $this->offer === null ? $this->chain : $this->offer->chain;
    }

    /**
     * Název položky: název akce, nebo vlastní název.
     */
    public function displayName(): string
    {
        return $this->offer->name ?? (string) $this->custom_name;
    }
}
