<?php

/**
 * Továrna uživatelů pro testy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** Heslo všech vytvořených uživatelů — v testech jako PASSWORD. */
    public const PASSWORD = 'password';

    /** Hash hesla — počítá se jednou, bcrypt je záměrně pomalý. */
    protected static ?string $passwordHash = null;

    /**
     * Výchozí uživatel s heslem PASSWORD.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'remember_token' => Str::random(10),
        ];
    }
}
