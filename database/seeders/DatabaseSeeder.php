<?php

/**
 * Vývojová data — jen pro lokální databázi.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Vytvoří vývojového uživatele test@example.com s heslem UserFactory::PASSWORD,
     * který spravuje katalog (R29), a výchozí produkty katalogu.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Testovací uživatel',
            'email' => 'test@example.com',
            'password' => UserFactory::PASSWORD,
            'is_admin' => true,
        ]);

        $this->call(CatalogSeeder::class);
    }
}
