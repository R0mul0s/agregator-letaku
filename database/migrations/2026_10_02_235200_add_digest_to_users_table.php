<?php

/**
 * E-mailový souhrn nových akcí (R42): jak často ho posílat a kdy přišel naposledy.
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
     * Přidá sloupce users.digest_frequency a users.digest_sent_at.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('digest_frequency', 10)->default('off')->after('min_discount_percent')->comment('App\Enums\DigestFrequency');
            $table->timestamp('digest_sent_at')->nullable()->after('digest_frequency')->comment('UTC, poslední odeslaný souhrn; akce nalezené později jsou „nové“');
        });
    }

    /**
     * Odstraní sloupce souhrnu.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['digest_frequency', 'digest_sent_at']);
        });
    }
};
