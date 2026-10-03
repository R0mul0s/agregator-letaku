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

use App\Domain\Catalog\Actions\ImportCategories;
use App\Domain\Chains\Actions\ImportStores;
use App\Domain\Digest\Actions\SendDigests;
use App\Domain\Offers\Actions\ImportChainOffers;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Http\Requests\CronRequest;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Throwable;

class CronController extends Controller
{
    /**
     * Stáhne akce jednoho obchodu (`?chain=tesco`). Každý obchod má vlastní cron — všechny
     * najednou trvají ~1,5 minuty a nemusí se vejít do limitu požadavku na hostingu (O8).
     */
    public function importOffers(CronRequest $request, ImportChainOffers $import): Response
    {
        $request->validate(['chain' => ['required']]);
        $chain = $request->chain();
        $this->extendTimeLimit();

        try {
            $run = $import($chain);
        } catch (Throwable $error) {
            // Chyba je zapsaná v scrape_runs; cron WebAdminu uvidí neúspěšný stav
            report($error);

            return $this->text(__('app.import.failed', ['chain' => $chain->label(), 'error' => $error->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->text(__('app.import.offers_done', ['chain' => $chain->label(), 'count' => $run->offers_count]));
    }

    /**
     * Stáhne prodejny obchodu a akce platné v každé z nich (`?chain=kaufland`, R49) — před
     * stažením akcí obchodu, ať import ví, v kterých prodejnách akce platí.
     */
    public function importStores(CronRequest $request, ImportStores $import, SourceRegistry $sources): Response
    {
        $request->validate(['chain' => ['required', Rule::in(array_map(fn (Chain $chain): string => $chain->value, $sources->chainsWithStores()))]]);
        $chain = $request->chain();
        $this->extendTimeLimit();

        try {
            $result = $import($chain);
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
        } catch (Throwable $error) {
            report($error);

            return $this->text(__('app.import.categories_failed', ['error' => $error->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->text(__('app.import.categories_done', ['count' => $count]));
    }

    /**
     * Pošle e-mailové souhrny nových akcí (R42) — jednou denně po ranním stažení.
     */
    public function sendDigests(CronRequest $request, SendDigests $send): Response
    {
        $this->extendTimeLimit();

        try {
            $count = $send();
        } catch (Throwable $error) {
            report($error);

            return $this->text(__('app.digest.failed', ['error' => $error->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->text(__('app.digest.done', ['count' => $count]));
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
     * Odpověď jako prostý text bez cache.
     */
    private function text(string $text, int $status = Response::HTTP_OK): Response
    {
        return response($text."\n", $status, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']);
    }
}
