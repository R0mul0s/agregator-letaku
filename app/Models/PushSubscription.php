<?php

/**
 * Odběr upozornění v telefonu (web push, R66) — jeden prohlížeč na jednom zařízení. Adresa
 * patří push službě prohlížeče (Google, Apple, Mozilla, Microsoft), klíče k zašifrování zprávy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $endpoint
 * @property string $public_key Klíč p256dh prohlížeče (base64url)
 * @property string $auth_token Tajemství auth prohlížeče (base64url)
 * @property string $device Čitelný název zařízení („Chrome · Android“)
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
class PushSubscription extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'device',
    ];

    /**
     * Klíče se ven neposílají — stránka potřebuje jen adresu, aby poznala vlastní zařízení.
     *
     * @var list<string>
     */
    protected $hidden = [
        'public_key',
        'auth_token',
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
     * Uživatel, kterému upozornění chodí.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
