<?php

/**
 * Průvodce prvním nastavením (R153): kdy uživatel průvodce dokončil nebo kartu „Dokončete
 * nastavení“ zavřel. Dosavadní účty průvodce nedostanou — označí se časem registrace.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Přidá sloupec onboarded_at a vyplní ho dosavadním účtům.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarded_at')->nullable()->after('last_seen_at')->comment('UTC, dokončení průvodce nebo zavření karty Dokončete nastavení');
        });

        DB::table('users')->update(['onboarded_at' => DB::raw('created_at')]);
    }

    /**
     * Odebere sloupec onboarded_at.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarded_at');
        });
    }
};
