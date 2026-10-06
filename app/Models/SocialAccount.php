<?php

/**
 * Účet u poskytovatele přihlášení (Google, Facebook) propojený s uživatelem (R96).
 * Páruje se podle ID u poskytovatele, ne podle e-mailu — ten se u poskytovatele může změnit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\SocialProvider;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property SocialProvider $provider
 * @property string $provider_user_id ID uživatele u poskytovatele (Google sub, Facebook id)
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
class SocialAccount extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'provider',
        'provider_user_id',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Uživatel, ke kterému účet patří.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
