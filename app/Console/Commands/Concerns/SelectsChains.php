<?php

/**
 * Výběr obchodů argumentem `chain` artisan příkazu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Enums\Chain;
use Illuminate\Console\Command;

/**
 * @mixin Command
 */
trait SelectsChains
{
    /**
     * Obchody z argumentu `chain`; bez argumentu všechny dostupné. Neznámý nebo nedostupný
     * obchod vypíše chybu a vrátí null.
     *
     * @param  list<Chain>  $available
     * @return list<Chain>|null
     */
    private function selectedChains(array $available): ?array
    {
        $requested = (array) $this->argument('chain');
        if ($requested === []) {
            return $available;
        }

        $chains = [];
        foreach ($requested as $value) {
            $chain = Chain::tryFrom(mb_strtolower((string) $value));
            if ($chain === null || ! in_array($chain, $available, true)) {
                $this->error(__('app.import.unknown_chain', [
                    'chain' => (string) $value,
                    'available' => implode(', ', array_map(fn (Chain $chain): string => $chain->value, $available)),
                ]));

                return null;
            }
            $chains[] = $chain;
        }

        return $chains;
    }
}
