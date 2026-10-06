<?php

/**
 * Úvodní stránka pro nepřihlášené (R44) — co Slevohlídka je a umí, co přinese registrace,
 * a ukázka skutečných akcí s nejvyšší slevou. Přihlášený má na stejné adrese Moje slevy.
 * Hledání v hlavním pruhu, živá ukázka hlídání a hra „Co je levnější?“ (R90). Data
 * z akcí jsou v cache do dalšího stažení (LandingSnapshot, R95).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\LandingSnapshot;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __construct(private readonly LandingSnapshot $snapshot) {}

    /**
     * Zobrazí úvodní stránku s počty, ukázkou akcí, ukázkou hlídání a hrou.
     */
    public function show(): Response
    {
        $data = $this->snapshot->get();

        return Inertia::render('Landing', [
            'urls' => [
                'register' => route('register', absolute: false),
                'login' => route('login', absolute: false),
                'offers' => route('offers', absolute: false),
                'suggestions' => route('offers.suggestions', absolute: false),
            ],
            'suggestMinLength' => config()->integer('letaky.offers.suggest_min_length'),
            'stats' => $data['stats'],
            'chains' => $data['chains'],
            // Logo obchodu odkazuje na jeho akce (R68) — robot jinak stránky obchodů najde jen
            // v sitemap (výběr obchodu ve Všech akcích jsou tlačítka)
            'chainUrls' => $data['chainUrls'],
            'topOffers' => $data['topOffers'],
            'demo' => [
                'url' => route('watch-demo', absolute: false),
                ...$data['demo'],
            ],
            'historyWeeks' => config()->integer('letaky.price_history.weeks'),
            'quiz' => $data['quiz'],
        ]);
    }
}
