<?php

/**
 * Katalog produktů (R29, R30): produkty s pravidly, přiřazení nabídek k produktům
 * s ručními opravami a příznak admina, který katalog spravuje.
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
     * Vytvoří tabulky products, offer_product, offer_product_exclusions a sloupec users.is_admin.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('loyalty_programs')->comment('smí spravovat katalog produktů (R29)');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 100)->unique();
            $table->string('keywords')->comment('všechna slova musí být v nabídce, alternativy přes | (R18)');
            $table->string('variant_keywords')->nullable()->comment('chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)');
            $table->string('exclude_keywords')->nullable()->comment('kterékoli slovo nabídku vyřadí');
            $table->timestamps();
        });

        Schema::create('offer_product', function (Blueprint $table): void {
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->comment('App\Enums\MatchStatus');
            $table->boolean('is_manual')->default(false)->comment('přiřadil admin ručně — přepočet ho nemění (R30)');
            $table->timestamps();

            $table->primary(['offer_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('offer_product_exclusions', function (Blueprint $table): void {
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['offer_id', 'product_id']);
            $table->index('product_id');
        });
    }

    /**
     * Odstraní tabulky katalogu a sloupec users.is_admin.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_product_exclusions');
        Schema::dropIfExists('offer_product');
        Schema::dropIfExists('products');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }
};
