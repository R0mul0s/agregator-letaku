<?php

/**
 * Hledání bez výsledků (R171): co lidé hledají ve Všech akcích a chtějí hlídat v Hlídám, a nic
 * to nenašlo — podklad pro nové produkty katalogu a další obchody. Souhrnně bez vazby na
 * uživatele: text po TextNormalizer, kolikrát (jeden člověk nejvýš jednou za den) a kdy naposledy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Založí tabulku search_misses.
     */
    public function up(): void
    {
        Schema::create('search_misses', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 16)->comment('App\Enums\SearchMissSource — Všechny akce, nebo Hlídám');
            $table->string('term', 100)->comment('hledaný text po TextNormalizer — klíč');
            $table->string('text', 100)->comment('hledaný text k zobrazení, jak ho někdo naposledy napsal');
            $table->unsignedInteger('hits')->default(1)->comment('kolikrát se hledalo — jeden člověk nejvýš jednou za den');
            $table->timestamp('last_seen_at')->comment('UTC, poslední započítané hledání');
            $table->timestamp('dismissed_at')->nullable()->comment('UTC, admin hledání skryl; null = ukázat');
            $table->timestamps();

            $table->unique(['source', 'term']);
            $table->index('last_seen_at');
        });
    }

    /**
     * Odstraní tabulku search_misses.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_misses');
    }
};
