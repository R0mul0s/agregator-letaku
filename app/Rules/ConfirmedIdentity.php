<?php

/**
 * Účet bez hesla se před citlivou změnou potvrdil u poskytovatele přihlášení (R96,
 * IdentityConfirmation). Pravidlo je implicitní — pole s heslem zůstává prázdné, a přesto
 * se musí ověřit.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Account\IdentityConfirmation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ConfirmedIdentity implements ValidationRule
{
    /** Ověřit i prázdné nebo chybějící pole (Laravel čte vlastnost u ValidationRule). */
    public bool $implicit = true;

    public function __construct(private readonly IdentityConfirmation $confirmation) {}

    /**
     * Selže, když potvrzení u poskytovatele chybí nebo je starší než povolená doba.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->confirmation->isFresh(session()->driver())) {
            $fail(__('app.ui.account.social.confirm_required'));
        }
    }
}
