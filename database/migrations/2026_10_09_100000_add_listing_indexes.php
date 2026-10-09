<?php

/**
 * Indexy pro dotazy, které s historií akcí (R10, nic se nemaže) rostou (R113): dnes začínající
 * akce (`valid_from`), filtr Nové a hranice nových akcí (`created_at`), akce stažené obchodem
 * (`withdrawn_at`) a poslední úspěšné stažení v patičce každé stránky (`scrape_runs`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Přidá indexy.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->index('valid_from');
            $table->index('created_at');
            $table->index('withdrawn_at');
        });

        Schema::table('scrape_runs', function (Blueprint $table): void {
            $table->index(['status', 'finished_at']);
        });
    }

    /**
     * Odstraní indexy.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->dropIndex(['valid_from']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['withdrawn_at']);
        });

        Schema::table('scrape_runs', function (Blueprint $table): void {
            $table->dropIndex(['status', 'finished_at']);
        });
    }
};
