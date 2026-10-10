<?php

/**
 * Nejlepší slevy týdne (R128): `/tyden` přesměruje na aktuální týden, `/tyden/2026-41` ukáže
 * žebříček slev týdne, nejlepší slevy po obchodech a archiv předchozích týdnů. Veřejná
 * indexovaná stránka — každý týden má vlastní adresu (nový obsah pro vyhledávače, odkaz
 * na sociální sítě). Data skládá WeeklyDeals.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\IsoWeek;
use App\Domain\Offers\WeeklyDeals;
use App\Support\Seo\SeoMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WeeklyDealsController extends Controller
{
    public function __construct(private readonly WeeklyDeals $deals) {}

    /**
     * Přesměruje na aktuální týden — dočasně (302), za týden vede jinam.
     */
    public function current(): RedirectResponse
    {
        return redirect()->to($this->deals->url($this->deals->currentWeek()));
    }

    /**
     * Zobrazí nejlepší slevy týdne; týden mimo archiv (před prvním stažením, budoucí,
     * bez slev) je 404.
     */
    public function show(Request $request, string $week, SeoMeta $seo): Response
    {
        $isoWeek = IsoWeek::fromSlug($week);
        abort_if($isoWeek === null || ! $this->deals->has($isoWeek), HttpResponse::HTTP_NOT_FOUND);

        $weeks = $this->deals->weeks();
        $index = $this->indexOf($weeks, $isoWeek);
        $current = $this->deals->currentWeek();

        return Inertia::render('Weekly', [
            'heading' => $seo->heading($request),
            'week' => [
                ...$this->weekData($isoWeek),
                'current' => $isoWeek->equals($current),
            ],
            ...$this->deals->forWeek($isoWeek),
            // Archiv je od nejnovějšího: novější týden je před tímto, starší za ním
            'newerUrl' => isset($weeks[$index - 1]) ? $this->deals->url($weeks[$index - 1]) : null,
            'olderUrl' => isset($weeks[$index + 1]) ? $this->deals->url($weeks[$index + 1]) : null,
            'archive' => array_map(fn (IsoWeek $item): array => [
                ...$this->weekData($item),
                'url' => $this->deals->url($item),
                'active' => $item->equals($isoWeek),
            ], $weeks),
            'shareUrl' => $this->deals->url($isoWeek, absolute: true),
            'offersUrl' => route('offers', absolute: false),
        ]);
    }

    /**
     * Číslo, rok a rozsah dnů týdne.
     *
     * @return array{slug: string, number: int, year: int, range: string}
     */
    private function weekData(IsoWeek $week): array
    {
        return ['slug' => $week->slug(), 'number' => $week->number, 'year' => $week->year, 'range' => $week->range()];
    }

    /**
     * Pořadí týdne v archivu (has() zaručuje, že tam je).
     *
     * @param  list<IsoWeek>  $weeks
     */
    private function indexOf(array $weeks, IsoWeek $week): int
    {
        foreach ($weeks as $index => $item) {
            if ($item->equals($week)) {
                return $index;
            }
        }

        return 0;
    }
}
