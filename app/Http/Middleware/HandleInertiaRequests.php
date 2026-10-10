<?php

/**
 * Sdílená data pro všechny Inertia stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Offers\OfferPages;
use App\Domain\Offers\WeeklyDeals;
use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use App\Enums\OfferReportReason;
use App\Http\Controllers\WatchItemController;
use App\Models\OfferReport;
use App\Models\ScrapeRun;
use App\Models\User;
use App\Support\Legal\LegalDocuments;
use App\Support\Seo\SeoMeta;
use App\Support\SvgSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Kořenová Blade šablona (resources/views/app.blade.php).
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Položky hlavní navigace přihlášeného uživatele: název routy => klíč textu v app.ui.nav.
     * Na telefonu jsou ve spodní liště záložek (R66, TabBar.vue) s ikonou podle klíče (NavIcon.vue).
     */
    private const NAVIGATION = [
        'home' => 'home',
        'watch-items.index' => 'watch_items',
        'shopping-list.index' => 'shopping_list',
        'preferences' => 'preferences',
        'offers' => 'offers',
    ];

    /** Soubor s texty UI — jeho změna mění verzi Inertie (překlady se posílají jen jednou, R99). */
    private const TRANSLATIONS_FILE = 'lang/cs/app.php';

    /** Navigace nepřihlášeného (R44): veřejné Všechny akce a Nejlepší slevy týdne (R128). */
    private const GUEST_NAVIGATION = [
        'offers' => 'offers',
        WeeklyDeals::INDEX_ROUTE => 'weekly',
    ];

    /**
     * Verze assetů pro Inertii a service worker (R78): build Vite a texty UI. Texty se posílají
     * jen jednou (R99) — bez nich ve verzi by klient po nasazení s novými texty držel staré.
     */
    public function version(Request $request): ?string
    {
        return hash('xxh128', (string) parent::version($request).'|'.hash_file('xxh128', base_path(self::TRANSLATIONS_FILE)));
    }

    /**
     * Sdílí s každou stránkou texty UI, jazyk a zónu, přihlášeného uživatele,
     * stavovou zprávu Fortify a navigaci.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            // Texty UI (~50 kB) jen při celém načtení stránky — přechody Inertie je neposílají znovu,
            // klient si je drží (R99); změna textů mění verzi (version), stránka se načte celá
            'translations' => Inertia::once(fn () => trans('app.ui')),
            // Názvy a loga obchodů pro ChainLogo a výběr obchodu (public/images/chains)
            'chainInfo' => fn (): array => array_combine(
                array_map(fn (Chain $chain): string => $chain->value, Chain::cases()),
                array_map(fn (Chain $chain): array => [
                    'name' => $chain->label(),
                    'logo' => asset(sprintf(config()->string('letaky.chain_logo_path'), $chain->value)),
                    // Poměr stran pro width / height u <img> — bez nich logo po načtení posune obsah (R123)
                    'logoSize' => SvgSize::of(public_path(sprintf(config()->string('letaky.chain_logo_path'), $chain->value))),
                ], Chain::cases()),
            ),
            // Titulek veřejné stránky stejný jako ze serveru (R68) — <Head> ve Vue by ho jinak přepsal
            'seoTitle' => fn (): string => app(SeoMeta::class)->title($request),
            'locale' => app()->getLocale(),
            'timezone' => config('letaky.display_timezone'),
            'auth' => [
                'user' => $user instanceof User ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatarUrl' => $user->avatarUrl(),
                    // Neověřený e-mail: lišta s výzvou a novým odkazem (R51)
                    'emailVerified' => $user->hasVerifiedEmail(),
                ] : null,
                'verificationSendUrl' => route('verification.send', absolute: false),
                'logoutUrl' => route('logout', absolute: false),
                // Nepřihlášený má v hlavičce přihlášení a registraci (R44)
                'loginUrl' => route('login', absolute: false),
                'registerUrl' => route('register', absolute: false),
                // Účet je v menu pod avatarem vpravo nahoře (R40), ne v hlavní navigaci; na stránce
                // z menu je avatar „aktivní položkou“
                'accountUrl' => route('account', absolute: false),
                'accountActive' => $request->routeIs('account', 'catalog.*', 'announcements.*', 'users.*', 'reports.*', 'data-quality'),
                // Admin má v menu pod avatarem i katalog (R29) a zprávy od nás (R74, 11d) — v hlavičce
                // by se navigace zalamovala (R75)
                'catalogUrl' => $user instanceof User && $user->is_admin ? route('catalog.index', absolute: false) : null,
                'announcementsUrl' => $user instanceof User && $user->is_admin ? route('announcements.index', absolute: false) : null,
                // Přehled uživatelů s poslední aktivitou (R84)
                'usersUrl' => $user instanceof User && $user->is_admin ? route('users.index', absolute: false) : null,
                // Hlášení chyb v akcích a co uživatelé skrývají (R125) s počtem otevřených hlášení
                'reports' => $user instanceof User && $user->is_admin ? [
                    'url' => route('reports.index', absolute: false),
                    'open' => OfferReport::query()->open()->distinct()->count('offer_id'),
                ] : null,
                // Přehled kvality stažených dat (R129)
                'dataQualityUrl' => $user instanceof User && $user->is_admin ? route('data-quality', absolute: false) : null,
            ],
            // Hlášení chyby z karty akce (R125): adresa a důvody; nepřihlášený null — tlačítko nevidí
            'offerReports' => fn (): ?array => $user instanceof User ? [
                'storeUrl' => route('offer-reports.store', absolute: false),
                'reasons' => array_map(
                    fn (OfferReportReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
                    OfferReportReason::cases(),
                ),
            ] : null,
            // Nákupní seznam (R61): které akce v něm jsou — tlačítko na kartě akce ukáže stav;
            // název se nesmí krýt s propem stránky
            'shoppingList' => fn (): ?array => $user instanceof User ? [
                // Jen akce — vlastní položky (R130) akci nemají
                'offerIds' => $user->shoppingListItems()->whereNotNull('offer_id')->pluck('offer_id')->all(),
                'toggleUrl' => route('shopping-list.toggle', absolute: false),
                // Odškrtnutí udělaná bez připojení se odešlou, až je signál — z kterékoli stránky (R66)
                'syncUrl' => route('shopping-list.sync', absolute: false),
            ] : null,
            // Centrum upozornění (R74): zvonek v hlavičce s počtem nepřečtených, stejné číslo
            // na ikoně aplikace (lib/pwa.js); název se nesmí krýt s propem stránky
            'notificationCenter' => fn (): ?array => $user instanceof User ? [
                'url' => route('notifications.index', absolute: false),
                'unread' => $user->unreadNotifications()->count(),
                'active' => $request->routeIs('notifications.*'),
            ] : null,
            // Aplikace v telefonu (R66): service worker jen z buildu — s Vite dev serverem (HMR)
            // by ukládal soubory, které se při každé změně mění
            'pwa' => fn (): array => [
                'serviceWorkerUrl' => Vite::isRunningHot() ? null : route('service-worker', absolute: false),
                'refreshAfterMinutes' => config()->integer('letaky.pwa.refresh_after_minutes'),
                'updateCheckMinutes' => config()->integer('letaky.pwa.update_check_minutes'),
                'installSnoozeDays' => config()->integer('letaky.pwa.install_prompt_snooze_days'),
            ],
            // Patička (R51, R92): právní stránky, obchody s odkazy na jejich akce a čas posledního
            // stažení — název se nesmí krýt s propem stránky. Údaje provozovatele jsou na /kontakt
            // a v podmínkách (§ 435 OZ), patička na ně odkazuje
            'siteFooter' => fn (): array => [
                'chains' => array_map(fn (Chain $chain): array => [
                    'chain' => $chain->value,
                    'url' => app(OfferPages::class)->chainUrl($chain),
                ], app(SourceRegistry::class)->chainsWithOffers()),
                'lastImportAt' => ScrapeRun::lastFinishedAt()?->toIso8601String(),
                // Nejlepší slevy týdne (R128) — přihlášený je nemá v navigaci (spodní lišta je plná)
                'weeklyUrl' => route(WeeklyDeals::INDEX_ROUTE, absolute: false),
                'contactUrl' => route('contact', absolute: false),
                'termsUrl' => route('legal.terms', absolute: false),
                'privacyUrl' => route('legal.privacy', absolute: false),
            ],
            // Souhlas s cookies, Google Analytics (R52) a Clarity (R103) — měření jen na produkci, jinde bez ID
            'cookieConsent' => fn (): array => [
                'measurementId' => app()->isProduction() ? config('letaky.cookie_consent.google_measurement_id') : null,
                'clarityProjectId' => app()->isProduction() ? config('letaky.cookie_consent.clarity_project_id') : null,
                'version' => config()->integer('letaky.cookie_consent.version'),
                'maxAgeDays' => config()->integer('letaky.cookie_consent.max_age_days'),
                'redactedPaths' => config('letaky.cookie_consent.redacted_paths'),
                // Rovnou na kapitolu o cookies v zásadách (R69)
                'privacyUrl' => route('legal.privacy', absolute: false).'#'.LegalDocuments::COOKIES_SECTION,
            ],
            // Zpráva Fortify po akci: přeložený text (odkaz na obnovu hesla odeslán)
            // nebo kód (profile-information-updated, password-updated)
            'status' => fn (): ?string => $request->session()->get('status'),
            // „Vrátit“ v toastu po přidání hlídané položky (R71): adresa jejího smazání
            'statusUndo' => fn (): ?string => $request->session()->get(WatchItemController::UNDO_SESSION_KEY),
            'navigation' => fn (): array => array_map(
                fn (string $routeName, string $labelKey): array => [
                    'url' => route($routeName, absolute: false),
                    'key' => $labelKey,
                    'label' => 'nav.'.$labelKey,
                    // Ve spodní liště na telefonu hlavní položky přihlášeného (nepřihlášený má jen hamburger)
                    'tab' => $user !== null && array_key_exists($routeName, self::NAVIGATION),
                    'active' => $request->routeIs($routeName) || $request->routeIs(str_replace('.index', '.*', $routeName)),
                ],
                array_keys($this->navigation($user)),
                $this->navigation($user),
            ),
        ];
    }

    /**
     * Položky navigace: název routy => klíč textu; nepřihlášený jen Všechny akce (R44). Katalog
     * admina je v menu pod avatarem (R75).
     *
     * @return array<string, string>
     */
    private function navigation(?User $user): array
    {
        if ($user === null) {
            return self::GUEST_NAVIGATION;
        }

        return self::NAVIGATION;
    }
}
