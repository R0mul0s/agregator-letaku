<?php

/**
 * Centrum upozornění (R74): databázové notifikace Laravelu (tabulka notifications) a čas,
 * do kterého má uživatel nové akce zapsané v centru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Založí tabulku notifications a sloupec users.notified_at.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type')->comment('druh upozornění (databaseType notifikace, např. new_offers)');
            $table->morphs('notifiable');
            $table->text('data')->comment('JSON: hlídané položky a ID akcí v okamžiku upozornění');
            $table->timestamp('read_at')->nullable()->comment('UTC, kdy si uživatel upozornění přečetl');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->after('push_sent_at')
                ->comment('UTC, do kdy jsou nové akce zapsané v centru upozornění (R74), i když nebylo co zapsat');
        });
    }

    /**
     * Odstraní tabulku notifications a sloupec users.notified_at.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notified_at');
        });

        Schema::dropIfExists('notifications');
    }
};
