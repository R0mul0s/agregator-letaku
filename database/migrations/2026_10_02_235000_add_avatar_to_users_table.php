<?php

/**
 * Vlastní profilový obrázek uživatele (R40). Bez obrázku se ukazují iniciály.
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
     * Přidá sloupec users.avatar_path.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path', 100)->nullable()->after('email')->comment('soubor na disku local (storage/app/private), null = iniciály');
        });
    }

    /**
     * Odstraní sloupec users.avatar_path.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('avatar_path');
        });
    }
};
