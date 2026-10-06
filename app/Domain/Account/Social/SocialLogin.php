<?php

/**
 * Cesta k poskytovateli přihlášení a zpět (R96, Laravel Socialite). Před odchodem si do relace
 * uloží záměr (přihlásit, propojit, potvrdit) — návrat má pro všechny jednu adresu, kterou je
 * potřeba zapsat u poskytovatele. Nepřihlášenému bez účtu drží identitu do dokončení registrace.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Account\Social;

use App\Enums\SocialProvider;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Session\Session;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class SocialLogin
{
    /** Klíč relace se záměrem cesty k poskytovateli. */
    private const SESSION_INTENT = 'social.intent';

    /** Klíč relace s identitou, která čeká na dokončení registrace. */
    private const SESSION_PENDING = 'social.pending';

    /** Údaje, o které žádáme Facebook — výchozí seznam Socialite chce i pohlaví a odkaz na profil. */
    private const FACEBOOK_FIELDS = ['name', 'email'];

    /**
     * Parametry potvrzení totožnosti (SocialIntent::Confirm): Google nechá vybrat účet,
     * Facebook si znovu řekne o heslo — uložená relace u poskytovatele by jinak stačila sama.
     */
    private const CONFIRM_PARAMETERS = [
        'google' => ['prompt' => 'select_account'],
        'facebook' => ['auth_type' => 'reauthenticate'],
    ];

    public function __construct(private readonly Socialite $socialite) {}

    /**
     * Uloží záměr do relace a vrátí přesměrování k poskytovateli.
     *
     * @param  bool  $remember  Přihlásit se „Zapamatovat si mě“ (jen SocialIntent::Login)
     * @param  string|null  $section  Kotva stránky účtu, kam se vrátit (Link a Confirm)
     */
    public function redirect(Session $session, SocialProvider $provider, SocialIntent $intent, bool $remember = false, ?string $section = null): RedirectResponse
    {
        $session->put(self::SESSION_INTENT, [
            'provider' => $provider->value,
            'intent' => $intent->value,
            'remember' => $remember,
            'section' => $section,
        ]);

        $driver = $this->driver($provider);
        if ($intent === SocialIntent::Confirm && $driver instanceof AbstractProvider) {
            $driver->with(self::CONFIRM_PARAMETERS[$provider->value]);
        }

        return $driver->redirect();
    }

    /**
     * Vyzvedne záměr uložený před odchodem k poskytovateli. Bez záměru (návrat z jiné karty,
     * vypršelá relace) nebo s jiným poskytovatelem se bere přihlášení.
     *
     * @return array{intent: SocialIntent, remember: bool, section: string|null}
     */
    public function pullIntent(Session $session, SocialProvider $provider): array
    {
        $stored = $session->pull(self::SESSION_INTENT);
        $stored = is_array($stored) && ($stored['provider'] ?? null) === $provider->value ? $stored : [];

        return [
            'intent' => SocialIntent::tryFrom(is_string($stored['intent'] ?? null) ? $stored['intent'] : '') ?? SocialIntent::Login,
            'remember' => ($stored['remember'] ?? false) === true,
            'section' => is_string($stored['section'] ?? null) ? $stored['section'] : null,
        ];
    }

    /**
     * Přečte, kdo se u poskytovatele přihlásil. Zrušené přihlášení, neplatný stav (návrat
     * otevřený podruhé, vypršelá relace) i chyba spojení jsou SocialLoginRefused::FAILED.
     *
     * @throws SocialLoginRefused
     */
    public function identity(SocialProvider $provider): SocialIdentity
    {
        try {
            return SocialIdentity::fromSocialite($provider, $this->driver($provider)->user());
        } catch (InvalidStateException $exception) {
            throw new SocialLoginRefused(SocialLoginRefused::FAILED, previous: $exception);
        } catch (GuzzleException $exception) {
            // Chyba spojení nebo odmítnutý kód u poskytovatele — do logu, ať jde poznat změna API
            report($exception);

            throw new SocialLoginRefused(SocialLoginRefused::FAILED, previous: $exception);
        }
    }

    /**
     * Zapamatuje si identitu nepřihlášeného bez účtu do dokončení registrace.
     */
    public function rememberPending(Session $session, SocialIdentity $identity, bool $remember): void
    {
        $session->put(self::SESSION_PENDING, [...$identity->toArray(), 'remember' => $remember]);
    }

    /**
     * Identita čekající na dokončení registrace, null když žádná není.
     */
    public function pending(Session $session): ?SocialIdentity
    {
        return SocialIdentity::fromArray($session->get(self::SESSION_PENDING));
    }

    /**
     * Přihlásit po dokončení registrace se „Zapamatovat si mě“?
     */
    public function pendingRemember(Session $session): bool
    {
        $pending = $session->get(self::SESSION_PENDING);

        return is_array($pending) && ($pending['remember'] ?? false) === true;
    }

    /**
     * Zapomene identitu čekající na registraci (po dokončení nebo jiném přihlášení).
     */
    public function forgetPending(Session $session): void
    {
        $session->forget(self::SESSION_PENDING);
    }

    /**
     * Driver Socialite s adresou návratu z routy (absolutní z APP_URL, R67) a jen potřebnými údaji.
     */
    private function driver(SocialProvider $provider): Provider
    {
        $driver = $this->socialite->driver($provider->value);
        if ($driver instanceof AbstractProvider) {
            $driver->redirectUrl(route('social.callback', ['provider' => $provider]));
        }
        if ($driver instanceof FacebookProvider) {
            $driver->fields(self::FACEBOOK_FIELDS);
        }

        return $driver;
    }
}
