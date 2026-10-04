<?php

/**
 * Upozornění v telefonu — web push (R66): odběry prohlížečů (jeden na zařízení) a čas
 * posledního upozornění uživatele.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Založí tabulku push_subscriptions a sloupec users.push_sent_at.
     */
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique()->comment('adresa push služby prohlížeče (letaky.push.allowed_hosts)');
            $table->string('public_key', 100)->comment('klíč p256dh prohlížeče, base64url');
            $table->string('auth_token', 50)->comment('tajemství auth prohlížeče, base64url');
            $table->string('device', 100)->comment('čitelný název zařízení z User-Agentu (DeviceName)');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('push_sent_at')->nullable()->after('digest_sent_at')
                ->comment('UTC, poslední zpracované upozornění v telefonu (R66), i když nebylo co poslat');
        });
    }

    /**
     * Odstraní tabulku push_subscriptions a sloupec users.push_sent_at.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('push_sent_at');
        });

        Schema::dropIfExists('push_subscriptions');
    }
};
