<?php

/**
 * Artisan: stažení seznamu prodejen obchodů — obálka nad ImportChainStores.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsChains;
use App\Domain\Chains\Actions\ImportChainStores;
use App\Domain\Sources\SourceRegistry;
use Illuminate\Console\Command;
use Throwable;

class ImportStoresCommand extends Command
{
    use SelectsChains;

    /** @var string */
    protected $signature = 'letaky:import-stores {chain?* : obchody (kaufland…); bez nich všechny se zdrojem prodejen}';

    /** @var string */
    protected $description = 'Stáhne seznam prodejen obchodů a uloží ho';

    /**
     * Stáhne prodejny zvolených obchodů; selhání jednoho obchodu nezastaví ostatní.
     */
    public function handle(ImportChainStores $import, SourceRegistry $sources): int
    {
        $chains = $this->selectedChains($sources->chainsWithStores());
        if ($chains === null) {
            return self::INVALID;
        }

        $failed = false;
        foreach ($chains as $chain) {
            try {
                $count = $import($chain);
                $this->info(__('app.import.stores_done', ['chain' => $chain->label(), 'count' => $count]));
            } catch (Throwable $error) {
                report($error);
                $this->error(__('app.import.failed', ['chain' => $chain->label(), 'error' => $error->getMessage()]));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
