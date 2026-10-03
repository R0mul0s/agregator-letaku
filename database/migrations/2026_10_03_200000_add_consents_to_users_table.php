<?php

/**
 * Souhlasy uživatele (R51): přijetí podmínek užití při registraci a souhlas s obchodními
 * sděleními (udělení, verze textu, odvolání). Dosavadní účty se označí jako ověřené —
 * ověření e-mailu přibylo až teď a jejich adresy už souhrny dostávají.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Přidá sloupce souhlasů a ověří e-mail dosavadních účtů.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('terms_accepted_at')->nullable()->after('digest_sent_at')->comment('UTC, přijetí podmínek užití při registraci');
            $table->unsignedSmallInteger('terms_version')->nullable()->after('terms_accepted_at')->comment('letaky.legal.terms_version v době přijetí');
            $table->timestamp('marketing_consent_at')->nullable()->after('terms_version')->comment('UTC, souhlas s obchodními sděleními; null = bez souhlasu');
            $table->unsignedSmallInteger('marketing_consent_version')->nullable()->after('marketing_consent_at')->comment('letaky.legal.marketing_consent_version v době souhlasu');
            $table->timestamp('marketing_consent_withdrawn_at')->nullable()->after('marketing_consent_version')->comment('UTC, poslední odvolání souhlasu');
        });

        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => DB::raw('created_at')]);
    }

    /**
     * Odstraní sloupce souhlasů (ověření e-mailu dosavadních účtů zůstane).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['terms_accepted_at', 'terms_version', 'marketing_consent_at', 'marketing_consent_version', 'marketing_consent_withdrawn_at']);
        });
    }
};
