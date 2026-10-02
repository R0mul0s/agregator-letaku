<?php

/**
 * Chybové hlášky validace (Laravel).
 *
 * Obsahuje pravidla, která aplikace používá, a běžná pravidla, která se dají čekat.
 * Chybějící klíč se zobrazí jako holý text (validation.xyz) — to je bug, doplnit sem.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

return [
    'accepted' => 'Pole :attribute musí být potvrzené.',
    'array' => 'Pole :attribute musí být seznam.',
    'boolean' => 'Pole :attribute musí být ano, nebo ne.',
    'confirmed' => 'Potvrzení pole :attribute nesouhlasí.',
    'current_password' => 'Heslo není správné.',
    'date' => 'Pole :attribute musí být platné datum.',
    'decimal' => 'Pole :attribute musí mít :decimal desetinných míst.',
    'different' => 'Pole :attribute a :other se musí lišit.',
    'email' => 'Pole :attribute musí být platná e-mailová adresa.',
    'enum' => 'Zvolená hodnota pole :attribute není platná.',
    'exists' => 'Zvolená hodnota pole :attribute neexistuje.',
    'in' => 'Zvolená hodnota pole :attribute není platná.',
    'integer' => 'Pole :attribute musí být celé číslo.',
    'max' => [
        'array' => 'Pole :attribute smí mít nejvýš :max položek.',
        'numeric' => 'Pole :attribute smí být nejvýš :max.',
        'string' => 'Pole :attribute smí mít nejvýš :max znaků.',
    ],
    'min' => [
        'array' => 'Pole :attribute musí mít aspoň :min položek.',
        'numeric' => 'Pole :attribute musí být aspoň :min.',
        'string' => 'Pole :attribute musí mít aspoň :min znaků.',
    ],
    'numeric' => 'Pole :attribute musí být číslo.',
    'password' => [
        'letters' => 'Pole :attribute musí obsahovat aspoň jedno písmeno.',
        'mixed' => 'Pole :attribute musí obsahovat velké i malé písmeno.',
        'numbers' => 'Pole :attribute musí obsahovat aspoň jednu číslici.',
        'symbols' => 'Pole :attribute musí obsahovat aspoň jeden symbol.',
        'uncompromised' => 'Toto heslo se objevilo v úniku dat. Zvolte prosím jiné.',
    ],
    'required' => 'Pole :attribute je povinné.',
    'same' => 'Pole :attribute a :other se musí shodovat.',
    'string' => 'Pole :attribute musí být text.',
    'unique' => 'Tato hodnota pole :attribute už je použitá.',
    'url' => 'Pole :attribute musí být platná adresa URL.',

    'attributes' => [
        'name' => 'jméno',
        'email' => 'e-mail',
        'password' => 'heslo',
        'current_password' => 'současné heslo',
    ],
];
