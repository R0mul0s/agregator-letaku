<?php

/**
 * Soubory pro roboty (R45): robots.txt, sitemap.xml, llms.txt, security.txt (R68) a klíč IndexNow (R105). Generují se z rout, aby
 * nesly správnou doménu (APP_URL) a mimo produkci zakázaly indexaci celého webu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\OfferPageChanges;
use App\Domain\Offers\OfferPages;
use App\Enums\Chain;
use App\Models\Offer;
use App\Support\Seo\IndexNow;
use App\Support\Seo\PublicPages;
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
        '/hlidam', '/seznam', '/obchody', '/ucet', '/katalog', '/akce/naseptavac', '/ukazka-hlidani',
        '/cron/', '/health/', '/up', '/odhlaseni/', '/email/',
    ];

    /** Jak dlouho smí odpověď ležet v cache (sekundy) — obsah se mění jen se staženými akcemi. */
    private const CACHE_SECONDS = 3600;

    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly OfferPages $pages,
    ) {}

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
     * sitemap.xml: úvodní stránka, Všechny akce, akce jednotlivých obchodů a produktů katalogu
     * s akcemi na čistých adresách (R94), právní stránky (R51) a kontakt.
     * Datum změny stránky akcí je poslední přibytí nebo stažení akce na ní (R122), kontaktu datum
     * účinnosti právních textů (R68) — jen skutečná změna, jinak by Google datu přestal věřit.
     */
    public function sitemap(OfferPageChanges $changes): Response
    {
        $chainChanges = $changes->byChain();
        $productIds = $this->pages->productsWithOffers();
        $productChanges = $changes->byProduct($productIds);
        $offersModified = $chainChanges === [] ? null : max($chainChanges)->toAtomString();
        $legalModified = config('letaky.legal.effective_from');
        $urls = [
            ['loc' => SeoMeta::homeUrl(), 'lastmod' => $offersModified],
            ['loc' => route('offers'), 'lastmod' => $offersModified],
            ...array_map(fn (Chain $chain): array => [
                'loc' => $this->pages->chainUrl($chain, absolute: true),
                'lastmod' => ($chainChanges[$chain->value] ?? null)?->toAtomString(),
            ], $this->chainsWithCurrentOffers()),
            ...array_map(fn (int $productId): array => [
                'loc' => $this->pages->productUrl($productId, absolute: true),
                'lastmod' => ($productChanges[$productId] ?? null)?->toAtomString(),
            ], $productIds),
            // Podmínky a zásady ne — jsou noindex (R121)
            ...array_map(fn (string $route): array => ['loc' => route($route), 'lastmod' => $legalModified], PublicPages::indexedRoutes()),
        ];

        return response()
            ->view('crawlers.sitemap', ['urls' => $urls])
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
                'name' => $chain->genitive(),
                'url' => $this->pages->chainUrl($chain, absolute: true),
            ], $this->chainsWithCurrentOffers()),
        ])->render();

        return $this->text($content, 'text/markdown');
    }

    /**
     * security.txt (RFC 9116, R68): kam hlásit bezpečnostní chybu. Platnost se posouvá
     * s každým požadavkem — kontakt je e-mail provozovatele z konfigurace a udržuje se s ní.
     */
    public function securityTxt(): Response
    {
        $expires = CarbonImmutable::now('UTC')->startOfDay()->addDays(config()->integer('letaky.security_txt.expires_days'));
        $lines = [
            'Contact: mailto:'.config()->string('letaky.operator.email'),
            'Expires: '.$expires->format('Y-m-d\TH:i:s\Z'),
            'Preferred-Languages: '.config()->string('letaky.security_txt.languages'),
            'Canonical: '.route('security-txt'),
        ];

        return $this->text(implode("\n", $lines)."\n", 'text/plain');
    }

    /**
     * Klíč IndexNow (R105) na /{klíč}.txt — soubor s klíčem v kořeni webu dokazuje vyhledávači,
     * že ohlášení stránek posílá web sám. Jiný název než klíč z konfigurace = 404.
     */
    public function indexNowKey(string $key, IndexNow $indexNow): Response
    {
        abort_unless($indexNow->key() !== null && hash_equals($indexNow->key(), $key), Response::HTTP_NOT_FOUND);

        return $this->text($key, 'text/plain');
    }

    /**
     * Obchody, které teď mají akce s cenou — výpis obchodu bez nich by byl prázdný.
     *
     * @return list<Chain>
     */
    private function chainsWithCurrentOffers(): array
    {
        $chains = Offer::query()->active()->notExpired($this->calendar->today())->distinct()->pluck('chain')->all();

        return array_values(array_filter(Chain::cases(), fn (Chain $chain): bool => in_array($chain, $chains, true)));
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
