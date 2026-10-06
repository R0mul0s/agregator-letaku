<?php

/**
 * Kdo se přihlásil u poskytovatele (R96) — jen údaje, které Slevohlídka potřebuje:
 * ID u poskytovatele, jméno a e-mail. Obrázek ani další údaje z profilu nebereme.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Social;

use App\Enums\SocialProvider;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class SocialIdentity
{
    /**
     * @param  string|null  $email  null = poskytovatel e-mail neposlal (Facebook účet založený přes telefon)
     * @param  bool  $emailVerified  Ručí poskytovatel za to, že e-mail patří přihlášenému?
     */
    public function __construct(
        public SocialProvider $provider,
        public string $id,
        public string $name,
        public ?string $email,
        public bool $emailVerified,
    ) {}

    /**
     * Převede uživatele ze Socialite. Ověřený e-mail hlásí jen Google (`email_verified`
     * v odpovědi userinfo); Facebook e-mail neověřuje spolehlivě, bereme ho jako neověřený —
     * ověří ho odkaz jako u registrace heslem (R51) a k existujícímu účtu se sám nepřipojí.
     */
    public static function fromSocialite(SocialProvider $provider, SocialiteUser $user): self
    {
        $email = $user->getEmail();
        $email = is_string($email) && $email !== '' ? mb_strtolower($email) : null;
        $raw = $user instanceof AbstractUser ? $user->getRaw() : [];

        return new self(
            provider: $provider,
            id: (string) $user->getId(),
            name: trim((string) $user->getName()),
            email: $email,
            emailVerified: $email !== null && $provider === SocialProvider::Google && ($raw['email_verified'] ?? false) === true,
        );
    }

    /**
     * Uloží identitu do relace (dokončení registrace, R96).
     *
     * @return array{provider: string, id: string, name: string, email: string|null, emailVerified: bool}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'emailVerified' => $this->emailVerified,
        ];
    }

    /**
     * Načte identitu uloženou v relaci; null, když tam žádná není nebo nemá čekaný tvar.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data)) {
            return null;
        }

        $provider = SocialProvider::tryFrom(is_string($data['provider'] ?? null) ? $data['provider'] : '');
        if ($provider === null || ! is_string($data['id'] ?? null) || ! is_string($data['name'] ?? null)) {
            return null;
        }

        return new self(
            provider: $provider,
            id: $data['id'],
            name: $data['name'],
            email: is_string($data['email'] ?? null) ? $data['email'] : null,
            emailVerified: ($data['emailVerified'] ?? false) === true,
        );
    }
}
