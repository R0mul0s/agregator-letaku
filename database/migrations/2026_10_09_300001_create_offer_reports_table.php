<?php

/**
 * Hlášení chyb v akcích od uživatelů (R125): špatná cena, platnost, „není to sleva“… Admin je
 * vidí na stránce Hlášení a označí jako vyřešená.
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
     * Založí tabulku offer_reports.
     */
    public function up(): void
    {
        Schema::create('offer_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 32)->comment('App\Enums\OfferReportReason');
            $table->text('note')->nullable()->comment('nepovinný popis od uživatele');
            $table->timestamp('resolved_at')->nullable()->comment('UTC, kdy admin hlášení vyřešil; null = otevřené');
            $table->timestamps();

            // Jedno hlášení akce od uživatele — další ho přepíše
            $table->unique(['offer_id', 'user_id']);
            $table->index('resolved_at');
        });
    }

    /**
     * Odstraní tabulku offer_reports.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_reports');
    }
};
