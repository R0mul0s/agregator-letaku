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
use Illuminate\Support\Str;
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

    /** Nejvyšší délka uloženého popisu chyby ve znacích (sloupec TEXT má 64 kB). */
    private const ERROR_LENGTH = 2000;

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
     * Konec posledního stažení s některým ze stavů (UTC), volitelně jen jednoho obchodu;
     * null = žádné. Patička, sitemap, hlídání stahování i upozornění ho berou odsud (R113).
     *
     * @param  list<ScrapeStatus>  $statuses
     */
    public static function lastFinishedAt(array $statuses = [ScrapeStatus::Succeeded], ?Chain $chain = null): ?CarbonImmutable
    {
        $finishedAt = self::query()
            ->whereIn('status', $statuses)
            ->when($chain, fn ($query, Chain $chain) => $query->where('chain', $chain))
            ->max('finished_at');

        // Databáze ukládá čas v UTC (config/app.php)
        return is_string($finishedAt) ? CarbonImmutable::parse($finishedAt, 'UTC') : null;
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
     * Označí stažení za částečné (R54): nabídky se uložily, chybějící se ale neoznačily
     * jako stažené, protože jich bylo podezřele mnoho. Důvod se uloží jako chyba.
     */
    public function succeedPartially(int $offersCount, Throwable $warning): void
    {
        $this->update([
            'status' => ScrapeStatus::Partial,
            'offers_count' => $offersCount,
            'withdrawn_count' => 0,
            'error' => self::describe($warning),
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
            'error' => self::describe($error),
            'finished_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Popis chyby pro sloupec error, zkrácený — zpráva chyby databáze nese celý dotaz
     * se všemi hodnotami (hromadný zápis akcí má přes 1 MB) a TEXT by ji neuložil;
     * stažení by pak zůstalo „running“ (Kaufland 20. 10. 2026).
     */
    private static function describe(Throwable $error): string
    {
        return Str::limit($error::class.': '.$error->getMessage(), self::ERROR_LENGTH);
    }
}
