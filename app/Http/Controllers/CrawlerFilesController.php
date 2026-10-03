<?php

/**
 * Soubory pro roboty (R45): robots.txt, sitemap.xml a llms.txt. Generují se z rout, aby
 * nesly správnou doménu (APP_URL) a mimo produkci zakázaly indexaci celého webu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\LocalCalendar;
use App\Enums\Chain;
use App\Enums\ScrapeStatus;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Support\Seo\SeoMeta;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class CrawlerFilesController extends Controller
{
    /**
     * Cesty za přihlášením a technické adresy — robot na nich nemá co dělat. Přihlášení
     * a registrace zakázané nejsou: mají „noindex“, který by robot za zákazem neviděl.
     */
    private const DISALLOWED_PATHS = [
        '/hlidam', '/obchody', '/ucet', '/katalog', '/akce/naseptavac',
        '/cron/', '/health/', '/up', '/odhlaseni/', '/email/',
    ];

    /** Jak dlouho smí odpověď ležet v cache (sekundy) — obsah se mění jen se staženými akcemi. */
    private const CACHE_SECONDS = 3600;

    public function __construct(private readonly LocalCalendar $calendar) {}

    /**
     * robots.txt: na produkci veřejné stránky ano, soukromé a technické ne; jinde nic.
     */
    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        if (app()->isProduction()) {
            array_push($lines, 'Allow: /', ...array_map(fn (string $path): string => 'Disallow: '.$path, self::DISALLOWED_PATHS));
            array_push($lines, '', 'Sitemap: '.route('sitemap'));
        } else {
            // Vývoj a testovací prostředí se nesmí dostat do výsledků hledání
            $lines[] = 'Disallow: /';
        }

        return $this->text(implode("\n", $lines)."\n", 'text/plain');
    }

    /**
     * sitemap.xml: úvodní stránka, Všechny akce, akce jednotlivých obchodů a právní stránky (R51). Datum změny
     * je poslední úspěšné stažení akcí.
     */
    public function sitemap(): Response
    {
        $lastModified = $this->lastImport()?->toAtomString();
        $urls = [
            SeoMeta::homeUrl(),
            route('offers'),
            ...array_map(fn (Chain $chain): string => route('offers', ['chain' => $chain->value]), $this->chainsWithCurrentOffers()),
            route('legal.terms'),
            route('legal.privacy'),
        ];

        return response()
            ->view('crawlers.sitemap', ['urls' => $urls, 'lastModified' => $lastModified])
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }

    /**
     * llms.txt (llmstxt.org): stručný popis webu a jeho veřejných stránek v Markdownu
     * pro jazykové modely a AI vyhledávače.
     */
    public function llms(): Response
    {
        $content = view('crawlers.llms', [
            'homeUrl' => SeoMeta::homeUrl(),
            'chains' => array_map(fn (Chain $chain): array => [
                'name' => $chain->label(),
                'url' => route('offers', ['chain' => $chain->value]),
            ], $this->chainsWithCurrentOffers()),
        ])->render();

        return $this->text($content, 'text/markdown');
    }

    /**
     * Obchody, které teď mají akce s cenou — Albert má jen zmínky v letácích (R36),
     * jeho výpis by byl prázdný.
     *
     * @return list<Chain>
     */
    private function chainsWithCurrentOffers(): array
    {
        $chains = Offer::query()->active()->notExpired($this->calendar->today())->distinct()->pluck('chain')->all();

        return array_values(array_filter(Chain::cases(), fn (Chain $chain): bool => in_array($chain, $chains, true)));
    }

    /**
     * Konec posledního úspěšného stažení akcí, nebo null.
     */
    private function lastImport(): ?CarbonImmutable
    {
        $finishedAt = ScrapeRun::query()->where('status', ScrapeStatus::Succeeded)->max('finished_at');

        return $finishedAt === null ? null : CarbonImmutable::parse((string) $finishedAt, 'UTC');
    }

    /**
     * Textová odpověď v UTF-8 s cache.
     */
    private function text(string $content, string $type): Response
    {
        return response($content, Response::HTTP_OK, [
            'Content-Type' => $type.'; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
        ]);
    }
}
