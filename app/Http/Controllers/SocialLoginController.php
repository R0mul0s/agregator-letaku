<?php

/**
 * Přihlášení přes Google a Facebook (R96): odchod k poskytovateli a návrat. Návrat má pro
 * všechny záměry jednu adresu (zapsanou u poskytovatele) — podle záměru v relaci přihlásí
 * nebo pošle dokončit registraci, propojí účet, nebo potvrdí totožnost účtu bez hesla.
 * Odpojení účtu v Mém účtu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\Actions\LinkSocialAccount;
use App\Domain\Account\Actions\ResolveSocialLogin;
use App\Domain\Account\Actions\UnlinkSocialAccount;
use App\Domain\Account\IdentityConfirmation;
use App\Domain\Account\Social\SocialIdentity;
use App\Domain\Account\Social\SocialIntent;
use App\Domain\Account\Social\SocialLogin;
use App\Domain\Account\Social\SocialLoginRefused;
use App\Enums\SocialProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class SocialLoginController extends Controller
{
    /** Parametr adresy odchodu: přihlásit se „Zapamatovat si mě“ (`?zapamatovat=1`). */
    public const REMEMBER_PARAMETER = 'zapamatovat';

    /** Parametr adresy propojení a potvrzení: kotva sekce Mého účtu, kam se vrátit. */
    public const SECTION_PARAMETER = 'sekce';

    /** Klíč chyby na stránce přihlášení (Login.vue). */
    public const ERROR_KEY = 'social';

    /** Sekce Mého účtu, kam se jde vrátit; jiná hodnota parametru = výchozí. */
    private const ACCOUNT_SECTIONS = ['profil', 'zabezpeceni', 'zruseni-uctu'];

    /** Výchozí sekce po propojení a potvrzení — tam jsou propojené účty. */
    private const DEFAULT_SECTION = 'zabezpeceni';

    /** Kód stavu po propojení účtu — toast (R47, lang: ui.toast.messages). */
    public const STATUS_LINKED = 'social-linked';

    /** Kód stavu po odpojení účtu — toast (R47, lang: ui.toast.messages). */
    public const STATUS_UNLINKED = 'social-unlinked';

    /** Kód stavu po potvrzení totožnosti — toast (R47, lang: ui.toast.messages). */
    public const STATUS_CONFIRMED = 'identity-confirmed';

    /** Zrušené přihlášení u poskytovatele vrací parametr `error` (OAuth 2, RFC 6749 4.1.2.1). */
    private const OAUTH_ERROR_PARAMETER = 'error';

    public function __construct(private readonly SocialLogin $login) {}

    /**
     * Nepřihlášený odchází k poskytovateli přihlásit se nebo zaregistrovat.
     */
    public function redirect(Request $request, SocialProvider $provider): SymfonyRedirect
    {
        abort_unless($provider->isConfigured(), 404);

        return $this->login->redirect($request->session(), $provider, SocialIntent::Login, remember: $request->boolean(self::REMEMBER_PARAMETER));
    }

    /**
     * Přihlášený odchází k poskytovateli propojit účet (Můj účet).
     */
    public function link(Request $request, SocialProvider $provider): SymfonyRedirect
    {
        abort_unless($provider->isConfigured(), 404);

        return $this->login->redirect($request->session(), $provider, SocialIntent::Link, section: $this->section($request));
    }

    /**
     * Účet bez hesla odchází k poskytovateli potvrdit totožnost před citlivou změnou.
     */
    public function confirm(Request $request, SocialProvider $provider): SymfonyRedirect
    {
        abort_unless($provider->isConfigured(), 404);
        abort_unless($this->user($request)->socialAccounts()->where('provider', $provider)->exists(), 404);

        return $this->login->redirect($request->session(), $provider, SocialIntent::Confirm, section: $this->section($request));
    }

    /**
     * Návrat od poskytovatele. Chyba skončí u nepřihlášeného na stránce přihlášení,
     * u přihlášeného toastem v Mém účtu.
     */
    public function callback(Request $request, SocialProvider $provider, ResolveSocialLogin $resolve, LinkSocialAccount $link, IdentityConfirmation $confirmation): RedirectResponse
    {
        abort_unless($provider->isConfigured(), 404);
        ['intent' => $intent, 'remember' => $remember, 'section' => $section] = $this->login->pullIntent($request->session(), $provider);
        $user = $request->user();

        try {
            if ($request->filled(self::OAUTH_ERROR_PARAMETER)) {
                throw new SocialLoginRefused(SocialLoginRefused::CANCELLED);
            }
            $identity = $this->login->identity($provider);

            if (! $user instanceof User) {
                return $this->logIn($request, $identity, $remember, $resolve);
            }
            if ($intent === SocialIntent::Link) {
                $link->handle($user, $identity);

                return $this->toAccount($section)->with('status', self::STATUS_LINKED);
            }
            if ($intent === SocialIntent::Confirm) {
                $confirmation->confirmWith($user, $identity, $request->session());

                return $this->toAccount($section)->with('status', self::STATUS_CONFIRMED);
            }

            // Přihlášený, který přišel z přihlášení v jiné kartě — už je, kde má být
            return redirect()->intended(config()->string('fortify.home'));
        } catch (SocialLoginRefused $refused) {
            return $user instanceof User
                ? $this->toAccount($section)->with('status', $refused->userMessage())
                : to_route('login')->withErrors([self::ERROR_KEY => $refused->userMessage()]);
        }
    }

    /**
     * Odpojí účet poskytovatele (Můj účet); poslední způsob přihlášení odpojit nejde.
     */
    public function unlink(Request $request, SocialProvider $provider, UnlinkSocialAccount $unlink): RedirectResponse
    {
        try {
            $unlink->handle($this->user($request), $provider);
        } catch (SocialLoginRefused $refused) {
            return back()->with('status', $refused->userMessage());
        }

        return back()->with('status', self::STATUS_UNLINKED);
    }

    /**
     * Přihlásí uživatele s propojeným účtem (nebo ověřeným e-mailem); bez účtu pošle
     * dokončit registraci se souhlasy (R51).
     *
     * @throws SocialLoginRefused
     */
    private function logIn(Request $request, SocialIdentity $identity, bool $remember, ResolveSocialLogin $resolve): RedirectResponse
    {
        $user = $resolve->handle($identity);
        if ($user === null) {
            $this->login->rememberPending($request->session(), $identity, $remember);

            return to_route('social.register');
        }

        $this->login->forgetPending($request->session());
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(config()->string('fortify.home'));
    }

    /**
     * Přesměrování na sekci Mého účtu.
     */
    private function toAccount(?string $section): RedirectResponse
    {
        return redirect()->to(route('account').'#'.($section ?? self::DEFAULT_SECTION));
    }

    /**
     * Sekce Mého účtu z parametru adresy, neznámá = výchozí.
     */
    private function section(Request $request): string
    {
        $section = $request->string(self::SECTION_PARAMETER)->toString();

        return in_array($section, self::ACCOUNT_SECTIONS, true) ? $section : self::DEFAULT_SECTION;
    }

    /**
     * Přihlášený uživatel (routy propojení a potvrzení jsou za middlewarem auth).
     */
    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
