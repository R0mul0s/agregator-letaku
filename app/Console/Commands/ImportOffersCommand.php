<?php

/**
 * Artisan: stažení akčních nabídek obchodů — obálka nad ImportChainOffers.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsChains;
use App\Domain\Offers\Actions\ImportChainOffers;
use App\Domain\Sources\SourceRegistry;
use Illuminate\Console\Command;
use Throwable;

class ImportOffersCommand extends Command
{
    use SelectsChains;

    /** @var string */
    protected $signature = 'letaky:import-offers {chain?* : obchody (kaufland, tesco…); bez nich všechny se zdrojem}';

    /** @var string */
    protected $description = 'Stáhne akční nabídky obchodů a uloží je';

    /**
     * Stáhne nabídky zvolených obchodů. Selhání jednoho obchodu nezastaví ostatní —
     * je zapsané v scrape_runs i v logu a příkaz skončí chybou.
     */
    public function handle(ImportChainOffers $import, SourceRegistry $sources): int
    {
        $chains = $this->selectedChains($sources->chainsWithOffers());
        if ($chains === null) {
            return self::INVALID;
        }

        $failed = false;
        foreach ($chains as $chain) {
            try {
                $run = $import($chain);
                $this->info(__('app.import.offers_done', ['chain' => $chain->label(), 'count' => $run->offers_count]));
            } catch (Throwable $error) {
                report($error);
                $this->error(__('app.import.failed', ['chain' => $chain->label(), 'error' => $error->getMessage()]));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
