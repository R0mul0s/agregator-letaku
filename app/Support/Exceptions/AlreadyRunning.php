<?php

/**
 * Úloha cronu už běží (ExclusiveRun, R113) — souběžné spuštění se přeskočí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

final class AlreadyRunning extends RuntimeException
{
    /**
     * Výjimka pro úlohu s klíčem zámku.
     */
    public function __construct(public readonly string $task)
    {
        parent::__construct("Úloha {$task} už běží, souběžné spuštění se přeskočilo.");
    }
}
