<?php

/**
 * Hypermarkety Globusu jako prodejny (R131): akce Globusu se liší po hypermarketech (cenová pásma,
 * místní akce), uživatel si vybere ty, kam chodí. Kód je `gsoaId` z webu Globusu (API
 * `/houses/{gsoaId}/…`). Seznam se nestahuje — hypermarkety přibývají zřídka; nový patří sem
 * (nová migrace) i do `letaky.sources.globus.price_zones`. Prodejny Globus Fresh letáky nemají.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Hypermarkety k 10. 10. 2026: kód => [název, město]. */
    private const HOUSES = [
        '4001' => ['Brno', 'Brno'],
        '4002' => ['Praha-Černý Most', 'Praha'],
        '4003' => ['Praha-Zličín', 'Praha'],
        '4004' => ['Pardubice', 'Pardubice'],
        '4005' => ['Praha-Čakovice', 'Praha'],
        '4006' => ['Liberec', 'Liberec'],
        '4007' => ['Ostrava', 'Ostrava'],
        '4008' => ['Olomouc', 'Olomouc'],
        '4009' => ['České Budějovice', 'České Budějovice'],
        '4010' => ['Chomutov', 'Chomutov'],
        '4011' => ['Chotíkov u Plzně', 'Plzeň'],
        '4012' => ['Opava', 'Opava'],
        '4014' => ['Jenišov u Karlových Varů', 'Karlovy Vary'],
        '4015' => ['Trmice u Ústí nad Labem', 'Ústí nad Labem'],
        '4019' => ['Havířov', 'Havířov'],
        '4026' => ['Praha-Štěrboholy', 'Praha'],
    ];

    /**
     * Založí hypermarkety Globusu v tabulce stores.
     */
    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach (self::HOUSES as $code => [$name, $city]) {
            $rows[] = ['chain' => 'globus', 'code' => $code, 'name' => $name, 'city' => $city, 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('stores')->upsert($rows, ['chain', 'code'], ['name', 'city', 'updated_at']);
    }

    /**
     * Smaže hypermarkety Globusu, jejich vazby na akce a výběr uživatelů.
     */
    public function down(): void
    {
        DB::table('offer_stores')->whereIn('store_code', array_map(strval(...), array_keys(self::HOUSES)))->delete();
        DB::table('followed_chains')->where('chain', 'globus')->update(['store_codes' => null]);
        DB::table('stores')->where('chain', 'globus')->delete();
    }
};
