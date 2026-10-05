<?php

/**
 * Zprávy od nás (R74, etapa 11d): co admin poslal uživatelům do centra upozornění — přehled
 * odeslaných zpráv; záznamy uživatelů jsou v tabulce notifications.
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
     * Založí tabulku announcements.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('admin, který zprávu poslal');
            $table->string('title', 120);
            $table->text('body');
            $table->string('url', 500)->nullable()->comment('odkaz ze zprávy: cesta v aplikaci nebo https adresa');
            $table->string('category', 20)->comment('service = o službě všem, marketing = jen se souhlasem s obchodními sděleními');
            $table->boolean('push')->default(false)->comment('poslat i do telefonu (jen zpráva o službě)');
            $table->unsignedInteger('recipients')->default(0)->comment('kolik uživatelů zprávu dostalo do centra');
            $table->timestamps();
        });
    }

    /**
     * Odstraní tabulku announcements.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
