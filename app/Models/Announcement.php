<?php

/**
 * Zpráva od nás odeslaná do centra upozornění (R74, etapa 11d) — přehled pro admina.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id Admin, který zprávu poslal
 * @property string $title
 * @property string $body
 * @property string|null $url Odkaz ze zprávy: cesta v aplikaci nebo https adresa
 * @property AnnouncementCategory $category
 * @property bool $push Poslat i do telefonu
 * @property int $recipients Kolik uživatelů zprávu dostalo do centra
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Announcement extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'url',
        'category',
        'push',
        'recipients',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AnnouncementCategory::class,
            'push' => 'boolean',
            'recipients' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
