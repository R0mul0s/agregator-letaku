<?php

/**
 * Regiony COOP jako prodejny (R167): regionální letáky z coopclub.cz platí jen v prodejnách
 * družstev svého regionu, celostátní leták všude. Uživatel si v Mých obchodech vybere region
 * místo prodejny (seznam 1 450 prodejen s družstvem by chtěl požadavek na každou). Morava
 * a Slezsko nemá regionální leták s textem — kdo si ho vybere, uvidí jen celostátní akce.
 * Kódy musí sedět s `letaky.sources.coop.leaflets`.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Regiony k 25. 10. 2026: kód => [název, oblast]. Družstva regionů jsou v nápovědě výběru. */
    private const REGIONS = [
        'coop-sc' => ['Střední Čechy', 'Čechy'],
        'coop-jc' => ['Jižní Čechy', 'Čechy'],
        'coop-vc' => ['Východní Čechy', 'Čechy'],
        'coop-zc' => ['Západní Čechy', 'Čechy'],
        'coop-ms' => ['Morava a Slezsko', 'Morava a Slezsko'],
    ];

    /**
     * Založí regiony COOP v tabulce stores.
     */
    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach (self::REGIONS as $code => [$name, $city]) {
            $rows[] = ['chain' => 'coop', 'code' => $code, 'name' => $name, 'city' => $city, 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('stores')->upsert($rows, ['chain', 'code'], ['name', 'city', 'updated_at']);
    }

    /**
     * Smaže regiony COOP, jejich vazby na akce a výběr uživatelů.
     */
    public function down(): void
    {
        DB::table('offer_stores')->whereIn('store_code', array_keys(self::REGIONS))->delete();
        DB::table('followed_chains')->where('chain', 'coop')->update(['store_codes' => null]);
        DB::table('stores')->where('chain', 'coop')->delete();
    }
};
