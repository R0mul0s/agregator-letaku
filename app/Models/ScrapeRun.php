<?php

/**
 * Jedno stažení nabídky obchodu. Nula položek u zdroje, který je obvykle má, je chyba (PLAN.md, kap. 4).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ScrapeRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * @property int $id
 * @property Chain $chain
 * @property ScrapeStatus $status
 * @property int $offers_count
 * @property int $withdrawn_count
 * @property string|null $error
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class ScrapeRun extends Model
{
    /** @use HasFactory<ScrapeRunFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'chain',
        'status',
        'offers_count',
        'withdrawn_count',
        'error',
        'started_at',
        'finished_at',
    ];

    /**
     * Přetypování sloupců.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chain' => Chain::class,
            'status' => ScrapeStatus::class,
            'offers_count' => 'integer',
            'withdrawn_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Založí záznam o právě začínajícím stažení.
     */
    public static function start(Chain $chain): self
    {
        return self::create([
            'chain' => $chain,
            'status' => ScrapeStatus::Running,
            'started_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Označí stažení za úspěšné s počtem uložených a stažených (R16) nabídek.
     */
    public function succeed(int $offersCount, int $withdrawnCount): void
    {
        $this->update([
            'status' => ScrapeStatus::Succeeded,
            'offers_count' => $offersCount,
            'withdrawn_count' => $withdrawnCount,
            'finished_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Označí stažení za neúspěšné a uloží popis chyby.
     */
    public function fail(Throwable $error): void
    {
        $this->update([
            'status' => ScrapeStatus::Failed,
            'error' => $error::class.': '.$error->getMessage(),
            'finished_at' => CarbonImmutable::now(),
        ]);
    }
}
