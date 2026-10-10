<?php

/**
 * URL pro cron WebAdminu Websupportu (R20) — hosting nemá SSH ani scheduler, cron umí jen
 * zavolat URL. Každá úloha je Action volatelná i z artisan příkazu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\Actions\RunMaintenance;
use App\Domain\Catalog\Actions\ImportCategories;
use App\Domain\Chains\Actions\ImportStores;
use App\Domain\Notifications\Actions\RunNotificationChannels;
use App\Domain\Offers\Actions\ImportChainOffers;
use App\Domain\Offers\Exceptions\ImportAlreadyRunning;
use App\Enums\ScrapeStatus;
use App\Http\Requests\CronRequest;
use App\Support\Deadline;
use App\Support\Exceptions\AlreadyRunning;
use Illuminate\Http\Response;
use Throwable;

class CronController extends Controller
{
    /**
     * Stáhne akce jednoho obchodu (`?chain=tesco`). Každý obchod má vlastní cron — všechny
     * najednou trvají ~1,5 minuty a nemusí se vejít do limitu požadavku na hostingu (O8).
     */
    public function importOffers(CronRequest $request, ImportChainOffers $import): Response
    {
        $chain = $request->chain();
        $this->extendTimeLimit();

        try {
            $run = $import($chain);
        } catch (ImportAlreadyRunning) {
            return $this->alreadyRunning();
        } catch (Throwable $error) {
            // Chyba je zapsaná v scrape_runs; cron WebAdminu uvidí neúspěšný stav
            report($error);

            return $this->text(__('app.import.failed', ['chain' => $chain->label(), 'error' => $error->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Částečné stažení (R54): nabídky jsou uložené, upozorní /health/imports a log
        return $this->text($run->status === ScrapeStatus::Partial
            ? __('app.import.offers_partial', ['chain' => $chain->label(), 'count' => $run->offers_count, 'error' => $run->error])
            : __('app.import.offers_done', ['chain' => $chain->label(), 'count' => $run->offers_count]));
    }

    /**
     * Stáhne prodejny obchodu a akce platné v každé z nich (`?chain=kaufland`, R49) — před
     * stažením akcí obchodu, ať import ví, v kterých prodejnách akce platí.
     */
    public function importStores(CronRequest $request, ImportStores $import): Response
    {
        $chain = $request->chain();
        $this->extendTimeLimit();

        try {
            $result = $import($chain);
        } catch (AlreadyRunning) {
            return $this->alreadyRunning();
        } catch (Throwable $error) {
            report($error);

            return $this->text(__('app.import.failed', ['chain' => $chain->label(), 'error' => $error->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->text(__('app.import.stores_done', ['chain' => $chain->label(), ...$result]));
    }

    /**
     * Stáhne strom kategorií katalogu z e-shopu Tesco (R28) — stačí občas.
     */
    public function importCategories(CronRequest $request, ImportCategories $import): Response
    {
        $this->extendTimeLimit();

        try {
            $count = $import();
        } catch (AlreadyRunning) {
            return $this->alreadyRunning();
        } catch (Throwable $error) {
            report($error);

            return $this->text(__('app.import.categories_failed', ['error' => $error->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->text(__('app.import.categories_done', ['count' => $count]));
    }

    /**
     * Pošle e-mailové souhrny nových akcí (R42) jedné dávce uživatelů (R54) — každou hodinu 6:30–22:30 kvůli okamžitým upozorněním (R58).
     * Stejný cron zapisuje záznamy centra upozornění (R74) a posílá upozornění v telefonu (R66),
     * ať na hostingu nepřibývá další úloha; chyba jednoho kanálu ostatní nezastaví. Souběžné
     * volání odpoví 409 a nic nepošle (R113).
     */
    public function sendDigests(CronRequest $request, RunNotificationChannels $channels): Response
    {
        $this->extendTimeLimit();

        try {
            // Kdyby hosting požadavek ukončil, souhrny a telefon na konci by nedoběhly vůbec (R106)
            $results = $channels(Deadline::in(config()->integer('letaky.cron.work_seconds')));
        } catch (AlreadyRunning) {
            return $this->alreadyRunning();
        }

        $lines = [];
        foreach ($results as $channel => $result) {
            $lines[] = $result instanceof Throwable
                ? __("app.$channel.failed", ['error' => $result->getMessage()])
                : __("app.$channel.done", ['count' => $result]);
        }
        $failed = array_any($results, fn (int|Throwable $result): bool => $result instanceof Throwable);

        return $this->text(implode("\n", $lines), $failed ? Response::HTTP_INTERNAL_SERVER_ERROR : Response::HTTP_OK);
    }

    /**
     * Úklid vypršelých relací a odkazů pro obnovu hesla (R53), surových odpovědí starých
     * akcí (R113) a starých statistik letáků (R129), jednou denně.
     */
    public function pruneSessions(CronRequest $request, RunMaintenance $maintenance): Response
    {
        $result = $maintenance();

        return $this->text(implode("\n", [
            __('app.maintenance.sessions_pruned', ['count' => $result['sessions']]),
            __('app.maintenance.offer_raw_pruned', ['count' => $result['offer_raw']]),
            __('app.maintenance.leaflet_stats_pruned', ['count' => $result['leaflet_stats']]),
        ]));
    }

    /**
     * Prodlouží limit běhu PHP — stažení Tesca trvá ~45 s. Hosting může limit
     * nebo timeout proxy vynutit i tak (O8).
     */
    private function extendTimeLimit(): void
    {
        set_time_limit(config()->integer('letaky.cron.time_limit_seconds'));
    }

    /**
     * Úloha už běží (R57, R113) — 409, ať cron WebAdminu souběh ukáže, ale nic se nestalo dvakrát.
     */
    private function alreadyRunning(): Response
    {
        return $this->text(__('app.cron.already_running'), Response::HTTP_CONFLICT);
    }

    /**
     * Odpověď jako prostý text bez cache.
     */
    private function text(string $text, int $status = Response::HTTP_OK): Response
    {
        return response($text."\n", $status, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
