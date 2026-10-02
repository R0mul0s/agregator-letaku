<?php

/**
 * Hlídaná položka uživatele — název a pravidla párování s nabídkami (R18).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WatchItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $keywords
 * @property string|null $variant_keywords
 * @property string|null $exclude_keywords
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class WatchItem extends Model
{
    /** @use HasFactory<WatchItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'keywords',
        'variant_keywords',
        'exclude_keywords',
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
     * Vlastník položky.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
