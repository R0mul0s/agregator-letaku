<?php

/**
 * Hledání, které nic nenašlo (R171) — souhrnně za text a zdroj, bez vazby na uživatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\SearchMissSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property SearchMissSource $source
 * @property string $term Hledaný text po TextNormalizer — klíč
 * @property string $text Hledaný text k zobrazení, jak ho někdo naposledy napsal
 * @property int $hits
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class SearchMiss extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'source',
        'term',
        'text',
        'hits',
        'last_seen_at',
        'dismissed_at',
    ];

    /**
     * Výchozí hodnoty sloupců — přísný režim modelů jinak u nově založeného záznamu hlásí
     * chybějící atribut.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'hits' => 1,
        'dismissed_at' => null,
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => SearchMissSource::class,
            'last_seen_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
