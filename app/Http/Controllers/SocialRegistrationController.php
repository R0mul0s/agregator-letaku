<?php

/**
 * Dokončení registrace přes Google nebo Facebook (R96): kdo se u poskytovatele přihlásil
 * a účet ještě nemá, potvrdí jméno a souhlasy (R51) — bez nich účet vzniknout nesmí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\Actions\RegisterSocialUser;
use App\Domain\Account\AuthShowcase;
use App\Domain\Account\Social\SocialLogin;
use App\Domain\Account\Social\SocialLoginRefused;
use App\Http\Responses\RegisterResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SocialRegistrationController extends Controller
{
    public function __construct(private readonly SocialLogin $login) {}

    /**
     * Formulář dokončení registrace; bez identity v relaci (vypršela, otevřeno přímo)
     * zpátky na registraci.
     */
    public function show(Request $request, AuthShowcase $showcase): Response|RedirectResponse
    {
        $identity = $this->login->pending($request->session());
        if ($identity === null) {
            return to_route('register');
        }

        return Inertia::render('Auth/SocialRegister', [
            'identity' => [
                'provider' => $identity->provider->value,
                'name' => $identity->name,
                'email' => $identity->email,
                'emailVerified' => $identity->emailVerified,
            ],
            'urls' => [
                'submit' => route('social.register.store', absolute: false),
                'register' => route('register', absolute: false),
                'terms' => route('legal.terms', absolute: false),
                'privacy' => route('legal.privacy', absolute: false),
            ],
            // Panel vedle formuláře jako u registrace (R56)
            'showcase' => fn (): array => $showcase->toArray(),
        ]);
    }

    /**
     * Založí účet, přihlásí a pokračuje jako po registraci heslem (Hlídám s uvítáním, R55;
     * produkt z karty akce, R60).
     */
    public function store(Request $request, RegisterSocialUser $register): SymfonyResponse
    {
        $identity = $this->login->pending($request->session());
        if ($identity === null) {
            return to_route('register');
        }

        try {
            $user = $register->handle($identity, $request->only(['name', 'terms', 'marketing']));
        } catch (SocialLoginRefused $refused) {
            return to_route('login')->withErrors([SocialLoginController::ERROR_KEY => $refused->userMessage()]);
        }

        $remember = $this->login->pendingRemember($request->session());
        $this->login->forgetPending($request->session());
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return app(RegisterResponse::class)->toResponse($request);
    }
}
