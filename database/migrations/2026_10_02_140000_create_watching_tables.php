<?php

/**
 * Hlídání slev: sledované obchody uživatele, jeho věrnostní karty a hlídané položky (R18, R19).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vytvoří tabulky followed_chains a watch_items a sloupec users.loyalty_programs.
     */
    public function up(): void
    {
        Schema::create('followed_chains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chain', 20)->comment('App\Enums\Chain');
            $table->string('store_format', 20)->nullable()->comment('App\Enums\StoreFormat; null = všechny typy prodejen');
            $table->boolean('include_online_only')->default(true)->comment('zobrazovat i akce jen z e-shopu (R4)');
            $table->timestamps();

            $table->unique(['user_id', 'chain']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->json('loyalty_programs')->nullable()->after('password')->comment('App\Enums\LoyaltyProgram[] — karty, které uživatel má');
        });

        Schema::create('watch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('keywords')->comment('všechna slova musí být v nabídce, alternativy přes |');
            $table->string('variant_keywords')->nullable()->comment('chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)');
            $table->string('exclude_keywords')->nullable()->comment('kterékoli slovo nabídku vyřadí');
            $table->timestamps();
        });
    }

    /**
     * Odstraní tabulky hlídání.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_items');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('loyalty_programs');
        });

        Schema::dropIfExists('followed_chains');
    }
};
