<?php

/**
 * Uživatel aplikace — účet přes Fortify (R12) s ověřeným e-mailem (R51), sledované obchody,
 * karty a hlídané položky (R18, R19), souhlasy s podmínkami a obchodními sděleními (R51).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\DigestFrequency;
use App\Enums\LoyaltyProgram;
use App\Enums\OffersSort;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $avatar_path Profilový obrázek na disku local (R40); null = iniciály
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property Collection<int, LoyaltyProgram>|null $loyalty_programs
 * @property bool $is_admin Smí spravovat katalog produktů (R29); nastavuje se příkazem, ne formulářem
 * @property OffersSort $offers_sort Řazení akcí v Mých slevách (R41)
 * @property int|null $min_discount_percent Moje slevy jen se slevou aspoň tolik %, null = všechny (R41)
 * @property DigestFrequency $digest_frequency Jak často posílat e-mailový souhrn (R42)
 * @property CarbonImmutable|null $digest_sent_at Poslední zpracovaný souhrn (UTC), i když nebylo co poslat (R54)
 * @property CarbonImmutable|null $terms_accepted_at Přijetí podmínek užití (R51)
 * @property int|null $terms_version Verze přijatých podmínek (letaky.legal.terms_version)
 * @property CarbonImmutable|null $marketing_consent_at Souhlas s obchodními sděleními (R51); null = bez souhlasu
 * @property int|null $marketing_consent_version Verze textu souhlasu (letaky.legal.marketing_consent_version)
 * @property CarbonImmutable|null $marketing_consent_withdrawn_at Poslední odvolání souhlasu
 * @property string|null $remember_token
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
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

    /**
     * Výchozí hodnoty nového účtu (jinak by je model znal až po načtení z databáze).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
        'avatar_path' => null,
        'offers_sort' => 'unit_price',
        'min_discount_percent' => null,
        'digest_frequency' => 'off',
        'digest_sent_at' => null,
        'terms_accepted_at' => null,
        'terms_version' => null,
        'marketing_consent_at' => null,
        'marketing_consent_version' => null,
        'marketing_consent_withdrawn_at' => null,
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
            'is_admin' => 'boolean',
            'offers_sort' => OffersSort::class,
            'min_discount_percent' => 'integer',
            'digest_frequency' => DigestFrequency::class,
            'digest_sent_at' => 'immutable_datetime',
            'terms_accepted_at' => 'immutable_datetime',
            'terms_version' => 'integer',
            'marketing_consent_at' => 'immutable_datetime',
            'marketing_consent_version' => 'integer',
            'marketing_consent_withdrawn_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
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
     * Adresa profilového obrázku, null bez obrázku (ukážou se iniciály). Název souboru
     * je při každém nahrání nový — parametr v obrázek v cache prohlížeče obnoví.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : route('account.avatar', ['v' => pathinfo($this->avatar_path, PATHINFO_FILENAME)], absolute: false);
    }

    /**
     * Souhlasí uživatel se zasíláním obchodních sdělení (R51)?
     */
    public function hasMarketingConsent(): bool
    {
        return $this->marketing_consent_at !== null;
    }

    /**
     * Má uživatel věrnostní kartu nebo aplikaci programu?
     */
    public function hasLoyaltyProgram(LoyaltyProgram $program): bool
    {
        return $this->loyalty_programs?->contains($program) ?? false;
    }

    /**
     * Prodejny vybrané u sledovaných obchodů (R49) — kódy jsou jedinečné napříč obchody.
     *
     * @return list<string>
     */
    public function selectedStoreCodes(): array
    {
        $codes = [];
        foreach ($this->followedChains()->get(['store_codes']) as $chain) {
            array_push($codes, ...($chain->store_codes ?? []));
        }

        return $codes;
    }
}
