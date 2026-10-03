<?php

/**
 * Obchod, který uživatel sleduje, s upřesněním typu prodejny a akcí e-shopu (R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use App\Enums\StoreFormat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Chain $chain
 * @property StoreFormat|null $store_format
 * @property bool $include_online_only
 * @property list<string>|null $store_codes Vybrané prodejny (R49); null = všechny
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class FollowedChain extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'chain',
        'store_format',
        'include_online_only',
        'store_codes',
    ];

    /**
     * Výchozí hodnoty sloupců — přísný režim modelů (shouldBeStrict) jinak u nově založeného
     * záznamu hlásí chybějící atribut.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'store_codes' => null,
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
            'include_online_only' => 'boolean',
            'store_codes' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Uživatel, který obchod sleduje.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
