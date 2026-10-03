<?php

/**
 * Příkaz letaky:import-stores — stáhne prodejny obchodu a akce platné v každé z nich (R49).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsChains;
use App\Domain\Chains\Actions\ImportStores;
use App\Domain\Sources\SourceRegistry;
use Illuminate\Console\Command;
use Throwable;

class ImportStoresCommand extends Command
{
    use SelectsChains;

    /** @var string */
    protected $signature = 'letaky:import-stores {chain?* : obchody (kaufland); bez nich všechny se zdrojem prodejen}';

    /** @var string */
    protected $description = 'Stáhne prodejny obchodu a seznam akcí platných v každé z nich';

    /**
     * Stáhne prodejny zvolených obchodů; chybu vypíše a skončí neúspěchem.
     */
    public function handle(ImportStores $import, SourceRegistry $sources): int
    {
        $chains = $this->selectedChains($sources->chainsWithStores());
        if ($chains === null) {
            return self::INVALID;
        }

        $failed = false;
        foreach ($chains as $chain) {
            try {
                $result = $import($chain);
                $this->info(__('app.import.stores_done', ['chain' => $chain->label(), ...$result]));
            } catch (Throwable $error) {
                report($error);
                $this->error(__('app.import.failed', ['chain' => $chain->label(), 'error' => $error->getMessage()]));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
