<?php

/**
 * Uživatel aplikace — účet přes Fortify (R12), sledované obchody a prodejny, karty
 * a hlídané položky (R18, R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoyaltyProgram;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property Collection<int, LoyaltyProgram>|null $loyalty_programs
 * @property string|null $remember_token
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'loyalty_programs',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'loyalty_programs' => AsEnumCollection::of(LoyaltyProgram::class),
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Vybrané prodejny (R3) — zatím jen u obchodů se seznamem prodejen.
     *
     * @return BelongsToMany<Store, $this>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class)->withTimestamps();
    }

    /**
     * Sledované obchody s upřesněním (R19).
     *
     * @return HasMany<FollowedChain, $this>
     */
    public function followedChains(): HasMany
    {
        return $this->hasMany(FollowedChain::class);
    }

    /**
     * Hlídané položky (R18).
     *
     * @return HasMany<WatchItem, $this>
     */
    public function watchItems(): HasMany
    {
        return $this->hasMany(WatchItem::class);
    }

    /**
     * Má uživatel věrnostní kartu nebo aplikaci programu?
     */
    public function hasLoyaltyProgram(LoyaltyProgram $program): bool
    {
        return $this->loyalty_programs?->contains($program) ?? false;
    }
}
