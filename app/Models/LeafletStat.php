<?php

/**
 * Statistika letáku za jedno stažení (R129) — počet uložených akcí a u letáků z PDF nebo SVG
 * nalezené a ověřené ceny. Zapisuje `RecordLeafletStats` při importu, čte přehled kvality dat.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $scrape_run_id
 * @property int $leaflet_id
 * @property Chain $chain
 * @property int $offers_count
 * @property int|null $tile_candidates
 * @property int|null $tiles_verified
 * @property CarbonImmutable $created_at
 * @property-read Leaflet $leaflet
 */
class LeafletStat extends Model
{
    /** Procenta z podílu. */
    private const PERCENT = 100;

    /** Řádek se jen zakládá, nemění. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'scrape_run_id',
        'leaflet_id',
        'chain',
        'offers_count',
        'tile_candidates',
        'tiles_verified',
        'created_at',
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
            'offers_count' => 'integer',
            'tile_candidates' => 'integer',
            'tiles_verified' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Leták, ke kterému statistika patří.
     *
     * @return BelongsTo<Leaflet, $this>
     */
    public function leaflet(): BelongsTo
    {
        return $this->belongsTo(Leaflet::class);
    }

    /**
     * Podíl ověřených cen v procentech; null u letáku z API nebo bez nalezené ceny.
     */
    public function verifiedPercent(): ?int
    {
        return $this->tile_candidates === null || $this->tile_candidates === 0 || $this->tiles_verified === null
            ? null
            : (int) round($this->tiles_verified / $this->tile_candidates * self::PERCENT);
    }
}
