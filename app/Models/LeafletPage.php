<?php

/**
 * Stránka letáku s textem — podklad pro zmínky bez ceny (R27).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LeafletPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $leaflet_id
 * @property int $number
 * @property string $text
 * @property string|null $image_url
 * @property string|null $page_url
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Leaflet $leaflet Cizí klíč je povinný, stránka bez letáku není
 */
class LeafletPage extends Model
{
    /** @use HasFactory<LeafletPageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'leaflet_id',
        'number',
        'text',
        'image_url',
        'page_url',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Leták, ke kterému stránka patří.
     *
     * @return BelongsTo<Leaflet, $this>
     */
    public function leaflet(): BelongsTo
    {
        return $this->belongsTo(Leaflet::class);
    }
}
