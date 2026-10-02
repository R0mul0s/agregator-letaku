<?php

/**
 * Příkaz letaky:import-categories — stáhne strom kategorií katalogu z e-shopu Tesco (R28).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Actions\ImportCategories;
use Illuminate\Console\Command;
use Throwable;

class ImportCategoriesCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:import-categories';

    /** @var string */
    protected $description = 'Stáhne strom kategorií e-shopu Tesco jako kategorie katalogu';

    /**
     * Stáhne a uloží kategorie; chybu vypíše a skončí neúspěchem.
     */
    public function handle(ImportCategories $import): int
    {
        try {
            $count = $import();
        } catch (Throwable $error) {
            report($error);
            $this->error(__('app.import.categories_failed', ['error' => $error->getMessage()]));

            return self::FAILURE;
        }

        $this->info(__('app.import.categories_done', ['count' => $count]));

        return self::SUCCESS;
    }
}
