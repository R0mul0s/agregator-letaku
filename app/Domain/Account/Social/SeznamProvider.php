<?php

/**
 * Přihlášení přes Seznam.cz pro Laravel Socialite (R98). Seznam má standardní OAuth 2.0
 * (https://vyvojari.seznam.cz/oauth/doc): žádost o token posílá JSON, ne formulář,
 * a údaje o uživateli vrací endpoint /user podle rozsahu `identity` — `oauth_user_id`
 * (stálé ID účtu), `email` (může být null), `firstname`, `lastname`.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Account\Social;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

final class SeznamProvider extends AbstractProvider
{
    /** Adresa přihlášení u Seznamu. */
    private const AUTH_URL = 'https://login.seznam.cz/api/v1/oauth/auth';

    /** Výměna kódu za token. */
    private const TOKEN_URL = 'https://login.seznam.cz/api/v1/oauth/token';

    /** Údaje o přihlášeném uživateli. */
    private const USER_URL = 'https://login.seznam.cz/api/v1/user';

    /**
     * Jen základní rozsah: ID účtu, e-mail a jméno. Telefon, obrázek ani datum narození nežádáme.
     *
     * @var list<string>
     */
    protected $scopes = ['identity'];

    /** Rozsahy odděluje mezera (OAuth 2.0). */
    protected $scopeSeparator = ' ';

    /**
     * Adresa, kam poslat uživatele k přihlášení.
     *
     * @param  string  $state
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(self::AUTH_URL, $state);
    }

    /**
     * Adresa výměny kódu za token.
     */
    protected function getTokenUrl(): string
    {
        return self::TOKEN_URL;
    }

    /**
     * Vymění kód za token. Seznam chce tělo požadavku jako JSON — výchozí formulář Socialite odmítne.
     *
     * @param  string  $code
     * @return array<string, mixed>
     */
    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::HEADERS => $this->getTokenHeaders($code),
            RequestOptions::JSON => $this->getTokenFields($code),
        ]);

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Načte údaje o uživateli podle tokenu.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(self::USER_URL, [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'bearer '.$token,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Převede odpověď Seznamu na uživatele Socialite. ID je `oauth_user_id` — e-mail se
     * u Seznamu může změnit, ID ne.
     *
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        $name = trim(implode(' ', array_filter([
            is_string($user['firstname'] ?? null) ? $user['firstname'] : null,
            is_string($user['lastname'] ?? null) ? $user['lastname'] : null,
        ])));

        return (new User)->setRaw($user)->map([
            'id' => $user['oauth_user_id'] ?? null,
            'name' => $name,
            'email' => $user['email'] ?? null,
        ]);
    }
}
