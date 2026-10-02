<?php

/**
 * Předvolby Mých slev (R41): výchozí řazení akcí a minimální sleva.
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
     * Přidá sloupce users.offers_sort a users.min_discount_percent.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('offers_sort', 20)->default('unit_price')->after('is_admin')->comment('App\Enums\OffersSort — řazení v Mých slevách');
            $table->unsignedTinyInteger('min_discount_percent')->nullable()->after('offers_sort')->comment('Moje slevy jen se slevou aspoň tolik %, null = všechny akce');
        });
    }

    /**
     * Odstraní sloupce předvoleb.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['offers_sort', 'min_discount_percent']);
        });
    }
};
