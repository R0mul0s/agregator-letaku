<?php

/**
 * Poslední aktivita uživatele (R84) pro přehled uživatelů admina. Relace se po odhlášení
 * a vypršení mažou, proto vlastní sloupec; dosavadním účtům se doplní z ještě uložených relací.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Přidá sloupec a doplní ho z tabulky relací.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_seen_at')->nullable()->after('notified_at')->index()->comment('UTC, poslední požadavek přihlášeného uživatele (zapisuje se nejvýš jednou za minutu)');
        });

        $lastActivity = DB::table('sessions')->whereNotNull('user_id')->groupBy('user_id')->pluck(DB::raw('MAX(last_activity)'), 'user_id');
        foreach ($lastActivity as $userId => $timestamp) {
            DB::table('users')->where('id', $userId)->update(['last_seen_at' => CarbonImmutable::createFromTimestampUTC((int) $timestamp)]);
        }
    }

    /**
     * Odstraní sloupec.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('last_seen_at');
        });
    }
};
