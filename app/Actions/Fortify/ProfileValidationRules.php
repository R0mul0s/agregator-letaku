<?php

/**
 * Pravidla jména a e-mailu účtu — stejná pro registraci heslem, přes účet poskytovatele
 * (R96) a změnu údajů v Mém účtu (R113, dřív ve třech kopiích).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /** Délka sloupců `users.name` a `users.email`. */
    private const PROFILE_TEXT_MAX_LENGTH = 255;

    /**
     * Pravidla jména.
     *
     * @return list<string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:'.self::PROFILE_TEXT_MAX_LENGTH];
    }

    /**
     * Pravidla e-mailu; adresa nesmí patřit jinému účtu.
     *
     * @return list<string|Unique>
     */
    protected function emailRules(?User $ignore = null): array
    {
        return ['required', 'string', 'email', 'max:'.self::PROFILE_TEXT_MAX_LENGTH, Rule::unique(User::class)->ignore($ignore?->id)];
    }
}
