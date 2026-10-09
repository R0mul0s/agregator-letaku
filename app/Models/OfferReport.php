<?php

/**
 * Hlášení chyby v akci od uživatele (R125) — admin ho vidí na stránce Hlášení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferReportReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $user_id
 * @property OfferReportReason $reason
 * @property string|null $note
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Offer $offer
 * @property-read User $user
 */
class OfferReport extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'offer_id',
        'user_id',
        'reason',
        'note',
        'resolved_at',
    ];

    /**
     * Výchozí hodnoty sloupců — přísný režim modelů jinak u nově založeného záznamu hlásí
     * chybějící atribut.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'note' => null,
        'resolved_at' => null,
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => OfferReportReason::class,
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Nahlášená akce.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Kdo hlásí.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Jen otevřená (nevyřešená) hlášení.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }
}
