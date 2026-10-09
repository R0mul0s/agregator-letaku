<?php

/**
 * Záznam běhu úlohy cronu pro hlídání `/health/tasks` (R115). Zapisuje Action úlohy, ať se
 * běh počítá, ať ji spustí cron URL, nebo artisan. Souběžné volání (`AlreadyRunning`) není
 * běh ani chyba — nezapíše se nic.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Support;

use App\Enums\CronTask;
use App\Models\TaskHeartbeat;
use App\Support\Exceptions\AlreadyRunning;
use Carbon\CarbonImmutable;
use Closure;
use Throwable;

final class TaskHeartbeats
{
    /**
     * Spustí úlohu a zapíše, jestli doběhla, nebo spadla; výjimku pošle dál.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public static function run(CronTask $task, Closure $work): mixed
    {
        try {
            $result = $work();
        } catch (AlreadyRunning $running) {
            throw $running;
        } catch (Throwable $error) {
            self::failed($task);

            throw $error;
        }

        self::succeeded($task);

        return $result;
    }

    /**
     * Úloha doběhla — i když v dávce nebylo co zpracovat.
     */
    public static function succeeded(CronTask $task): void
    {
        self::touch($task, 'succeeded_at');
    }

    /**
     * Úloha spadla; poslední úspěšný běh zůstane.
     */
    public static function failed(CronTask $task): void
    {
        self::touch($task, 'failed_at');
    }

    /**
     * Zapíše aktuální čas do sloupce úlohy; řádek založí, pokud ještě není.
     *
     * @param  'succeeded_at'|'failed_at'  $column
     */
    private static function touch(CronTask $task, string $column): void
    {
        TaskHeartbeat::query()->upsert([['task' => $task->value, $column => CarbonImmutable::now()]], ['task'], [$column]);
    }
}
