<?php

/**
 * Poslední úspěšný a neúspěšný běh úloh cronu pro hlídání `/health/tasks` (R115) — kanály
 * upozornění, denní úklid a kategorie. Jeden řádek na úlohu (App\Enums\CronTask).
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
     * Založí tabulku.
     */
    public function up(): void
    {
        Schema::create('task_heartbeats', function (Blueprint $table): void {
            $table->string('task', 40)->primary()->comment('App\Enums\CronTask');
            $table->timestamp('succeeded_at')->nullable()->comment('UTC, poslední úspěšný běh');
            $table->timestamp('failed_at')->nullable()->comment('UTC, poslední běh s chybou');
        });
    }

    /**
     * Smaže tabulku.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_heartbeats');
    }
};
