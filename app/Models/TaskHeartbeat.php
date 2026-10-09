<?php

/**
 * Poslední úspěšný a neúspěšný běh úlohy cronu (R115) — jeden řádek na úlohu, zapisuje
 * `TaskHeartbeats`, čte hlídání `/health/tasks`. Historie běhů se nevede.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\CronTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CronTask $task
 * @property CarbonImmutable|null $succeeded_at
 * @property CarbonImmutable|null $failed_at
 */
class TaskHeartbeat extends Model
{
    /** @var string */
    protected $primaryKey = 'task';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var bool */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'task',
        'succeeded_at',
        'failed_at',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task' => CronTask::class,
            'succeeded_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
