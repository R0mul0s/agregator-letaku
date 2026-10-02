<?php

/**
 * Zdroj nabídek obchodu — leták, akční stránka webu nebo akce e-shopu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\StoreFormat;
use Carbon\CarbonImmutable;
use Database\Factories\LeafletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property Chain $chain
 * @property LeafletKind $kind
 * @property string $external_id
 * @property string|null $title
 * @property StoreFormat|null $format
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property string|null $source_url
 * @property CarbonImmutable $fetched_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Leaflet extends Model
{
    /** @use HasFactory<LeafletFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'chain',
        'kind',
        'external_id',
        'title',
        'format',
        'valid_from',
        'valid_to',
        'source_url',
        'fetched_at',
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
            'kind' => LeafletKind::class,
            'format' => StoreFormat::class,
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'fetched_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Stránky letáku s textem (R27).
     *
     * @return HasMany<LeafletPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(LeafletPage::class);
    }

    /**
     * Nabídky z tohoto zdroje.
     *
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
