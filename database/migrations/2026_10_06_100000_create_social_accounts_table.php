<?php

/**
 * Přihlášení přes Google a Facebook (R96): propojené účty u poskytovatelů a heslo jako
 * nepovinné — účet založený přes Google heslo mít nemusí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vytvoří tabulku propojených účtů a povolí účet bez hesla.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->comment('poskytovatel přihlášení (SocialProvider): google, facebook');
            $table->string('provider_user_id')->comment('ID uživatele u poskytovatele (Google sub, Facebook id)');
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            // U jednoho poskytovatele nejvýš jeden propojený účet
            $table->unique(['user_id', 'provider']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->comment('null = účet bez hesla, přihlášení jen přes propojený účet (R96)')->change();
        });
    }

    /**
     * Odstraní tabulku; heslo zase povinné (účtům bez hesla se nastaví nepoužitelné).
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        DB::table('users')->whereNull('password')->update(['password' => '!']);
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->comment('')->change();
        });
    }
};
