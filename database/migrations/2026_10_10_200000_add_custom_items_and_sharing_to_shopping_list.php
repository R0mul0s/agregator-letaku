<?php

/**
 * Nákupní seznam s vlastními položkami a sdílením odkazem (R130): položka bez akce má vlastní
 * název („Almette“) a nepovinně obchod, uživatel má token veřejného odkazu na svůj seznam
 * (kdo ho má, seznam vidí a odškrtává).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vlastní název a obchod položky, akce nepovinná; token sdílení u uživatele.
     */
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('offer_id')->nullable()->change();
            $table->string('custom_name', 100)->nullable()->after('offer_id')->comment('vlastní položka bez akce; null = položka je akce');
            $table->string('chain', 20)->nullable()->after('custom_name')->comment('App\Enums\Chain — kde vlastní položku koupit; null = kdekoli');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('shopping_share_token', 64)->nullable()->unique()->comment('veřejný odkaz na nákupní seznam; nový token zneplatní starý odkaz');
        });
    }

    /**
     * Vrátí seznam jen s akcemi — vlastní položky smaže.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['shopping_share_token']);
            $table->dropColumn('shopping_share_token');
        });

        DB::table('shopping_list_items')->whereNull('offer_id')->delete();
        Schema::table('shopping_list_items', function (Blueprint $table): void {
            $table->dropColumn(['custom_name', 'chain']);
            $table->unsignedBigInteger('offer_id')->nullable(false)->change();
        });
    }
};
