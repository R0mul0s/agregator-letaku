<?php

/**
 * Indexy pro cron upozornění a úklid (R170): uživatelé se řadí a filtrují podle času posledního
 * upozornění v telefonu, souhrnu a záznamu centra upozornění (každou hodinu, roste s počtem
 * uživatelů); denní úklid maže záznamy centra upozornění podle `created_at`.
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
    /** Sloupce users s časem posledního upozornění. */
    private const USER_COLUMNS = ['push_sent_at', 'digest_sent_at', 'notified_at'];

    /**
     * Přidá indexy.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::USER_COLUMNS as $column) {
                $table->index($column);
            }
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    /**
     * Odebere indexy.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::USER_COLUMNS as $column) {
                $table->dropIndex([$column]);
            }
        });
    }
};
