<?php

/**
 * Společná pravidla pro nové heslo (registrace, obnova, změna).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Pravidla validace nového hesla — síla hesla z AppServiceProvider (délka, kontrola
     * proti únikům, R53) a potvrzení.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }
}
