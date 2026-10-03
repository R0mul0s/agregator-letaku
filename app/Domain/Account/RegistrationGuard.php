<?php

/**
 * Ochrana registrace proti botům bez captchy (R53): skryté pole, které vyplní jen robot,
 * a podepsaný čas načtení formuláře. Odeslání rychlejší, než stihne člověk vyplnit
 * formulář, příliš starý nebo podvržený token = registrace neprojde. Captcha by znamenala
 * cizí skript, cookies a úpravu CSP.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Account;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

final class RegistrationGuard
{
    /** Pole formuláře s podepsaným časem načtení. */
    public const TOKEN_FIELD = 'form_token';

    /** Skryté pole — člověk ho nevidí, robot vyplní všechno. */
    public const TRAP_FIELD = 'website';

    private const SECONDS_PER_MINUTE = 60;

    /**
     * Token s časem načtení formuláře, šifrovaný klíčem aplikace (nejde podvrhnout).
     */
    public function token(?CarbonImmutable $issuedAt = null): string
    {
        return Crypt::encryptString((string) ($issuedAt ?? CarbonImmutable::now())->getTimestamp());
    }

    /**
     * Vypadá odeslání jako od člověka? Prázdné skryté pole a token mezi nejkratší
     * a nejdelší dobou vyplňování.
     *
     * @param  array<string, mixed>  $input
     */
    public function passes(array $input): bool
    {
        if (filled($input[self::TRAP_FIELD] ?? null)) {
            return false;
        }

        $issuedAt = $this->issuedAt($input[self::TOKEN_FIELD] ?? null);
        if ($issuedAt === null) {
            return false;
        }

        $age = CarbonImmutable::now()->getTimestamp() - $issuedAt;

        return $age >= config()->integer('letaky.auth.registration.min_seconds')
            && $age <= config()->integer('letaky.auth.registration.max_age_minutes') * self::SECONDS_PER_MINUTE;
    }

    /**
     * Čas načtení z tokenu (unix), null = chybějící nebo podvržený token.
     */
    private function issuedAt(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $value = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
