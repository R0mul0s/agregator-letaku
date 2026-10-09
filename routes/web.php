<?php

/**
 * Webové routy aplikace. Přihlášení, registraci a obnovu hesla registruje Fortify (R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Offers\OfferPages;
use App\Enums\SocialProvider;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CrawlerFilesController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\HealthImportsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyAuthRedirectController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OffersController;
use App\Http\Controllers\OfferStoresController;
use App\Http\Controllers\OfferSuggestionsController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ShoppingPreferencesController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\SocialRegistrationController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WatchDemoController;
use App\Http\Controllers\WatchItemController;
use App\Http\Middleware\ReadOnlySession;
use App\Support\RateLimits;
use App\Support\Seo\IndexNow;
use Illuminate\Support\Facades\Route;

// Cron WebAdminu umí jen zavolat URL (R20, R38) — chráněné tokenem, bez něj 404;
// limit požadavků brání zkoušení tokenu (R45). Relaci nezapisuje — každé volání by jinak
// založilo řádek v sessions (R113)
Route::middleware(['throttle:'.RateLimits::CRON, ReadOnlySession::class])->group(function (): void {
    Route::get('/cron/import-offers', [CronController::class, 'importOffers'])->name('cron.import-offers');
    Route::get('/cron/import-stores', [CronController::class, 'importStores'])->name('cron.import-stores');
    Route::get('/cron/import-categories', [CronController::class, 'importCategories'])->name('cron.import-categories');
    Route::get('/cron/send-digests', [CronController::class, 'sendDigests'])->name('cron.send-digests');
    Route::get('/cron/prune-sessions', [CronController::class, 'pruneSessions'])->name('cron.prune-sessions');
});

Route::middleware('throttle:'.RateLimits::PUBLIC)->group(function (): void {
    // Soubory pro roboty (R45) — z rout kvůli doméně z APP_URL a zákazu indexace mimo produkci
    Route::get('/robots.txt', [CrawlerFilesController::class, 'robots'])->name('robots');
    Route::get('/sitemap.xml', [CrawlerFilesController::class, 'sitemap'])->name('sitemap');
    Route::get('/llms.txt', [CrawlerFilesController::class, 'llms'])->name('llms');
    // Kontakt pro hlášení bezpečnostních chyb (RFC 9116, R68)
    Route::get('/.well-known/security.txt', [CrawlerFilesController::class, 'securityTxt'])->name('security-txt');
    // Klíč IndexNow (R105) — vyhledávač si podle něj ověří, že ohlášení stránek posílá web sám
    Route::get('/{key}.txt', [CrawlerFilesController::class, 'indexNowKey'])
        ->where('key', IndexNow::KEY_PATTERN)
        ->name('indexnow-key');

    // Manifest pro přidání na plochu telefonu (R55)
    Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
    // Service worker a stránka bez připojení (R66) — service worker musí být v kořeni webu,
    // aby obsloužil celou aplikaci; seznam souborů k uložení skládá server z buildu
    Route::get('/sw.js', [ServiceWorkerController::class, 'script'])->name('service-worker');
    Route::get('/offline', [ServiceWorkerController::class, 'offline'])->name('offline');

    // Monitoring stahování (UptimeRobot) — veřejné, jen stav
    Route::get('/health/imports', HealthImportsController::class)->name('health.imports');

    // Veřejné (R44): úvodní stránka pro nepřihlášené (přihlášený tu má Moje slevy) a Všechny akce
    Route::get('/', HomeController::class)->name('home');
    Route::get('/akce', OffersController::class)->name('offers');
    // Čisté adresy pro vyhledávače (R94): obchod /akce/lidl, produkt katalogu /akce/pivo (OfferPages).
    // Našeptávač /akce/naseptavac je registrovaný až níž — tady se z části adresy vylučuje
    Route::get('/akce/{slug}', OffersController::class)
        ->where('slug', '(?!naseptavac$)[a-z0-9]+(?:-[a-z0-9]+)*')
        ->name(OfferPages::PAGE_ROUTE);

    // Právní stránky (R51)
    Route::get('/podminky', [LegalController::class, 'terms'])->name('legal.terms');
    Route::get('/ochrana-udaju', [LegalController::class, 'privacy'])->name('legal.privacy');
    // Kontakt na provozovatele (R72)
    Route::get('/kontakt', ContactController::class)->name('contact');

    // Staré anglické adresy Fortify (do R73) — záložky, vyhledávače a odkazy na obnovu hesla
    // z odeslaných e-mailů. Odkaz na ověření e-mailu přesměrovat nejde (podpis kryje i cestu),
    // platí ale jen hodinu a jde poslat znovu.
    foreach ([
        '/login' => 'login',
        '/register' => 'register',
        '/forgot-password' => 'password.request',
        '/reset-password/{token}' => 'password.reset',
        '/email/verify' => 'verification.notice',
    ] as $legacyPath => $target) {
        Route::get($legacyPath, LegacyAuthRedirectController::class)->defaults(LegacyAuthRedirectController::TARGET, $target);
    }

    // Odhlášení z e-mailů bez přihlášení (R51) — podepsaný odkaz; POST i od poštovního klienta (bez CSRF, bootstrap/app.php)
    Route::middleware('signed')->group(function (): void {
        Route::get('/odhlaseni/{user}/{list}', [UnsubscribeController::class, 'show'])->name('unsubscribe');
        Route::post('/odhlaseni/{user}/{list}', [UnsubscribeController::class, 'store'])->name('unsubscribe.store');
    });
});

// Přihlášení přes Google a Facebook (R96). Odchod k poskytovateli a dokončení registrace jen
// pro nepřihlášené; návrat má jednu adresu pro přihlášení, propojení i potvrzení (zapsanou
// u poskytovatele), záměr nese relace
Route::middleware('throttle:'.RateLimits::PUBLIC)->group(function (): void {
    $providers = array_column(SocialProvider::cases(), 'value');

    Route::middleware('guest')->group(function () use ($providers): void {
        Route::get('/prihlaseni/{provider}', [SocialLoginController::class, 'redirect'])->whereIn('provider', $providers)->name('social.redirect');
        Route::get('/registrace/dokonceni', [SocialRegistrationController::class, 'show'])->name('social.register');
        Route::post('/registrace/dokonceni', [SocialRegistrationController::class, 'store'])->name('social.register.store');
    });
    Route::get('/prihlaseni/{provider}/navrat', [SocialLoginController::class, 'callback'])->whereIn('provider', $providers)->name('social.callback');

    Route::middleware('auth')->group(function () use ($providers): void {
        Route::get('/ucet/propojit/{provider}', [SocialLoginController::class, 'link'])->whereIn('provider', $providers)->name('social.link');
        Route::get('/ucet/potvrdit/{provider}', [SocialLoginController::class, 'confirm'])->whereIn('provider', $providers)->name('social.confirm');
        Route::delete('/ucet/propojeni/{provider}', [SocialLoginController::class, 'unlink'])->whereIn('provider', $providers)->name('social.unlink');
    });
});

Route::get('/akce/naseptavac', OfferSuggestionsController::class)
    // Relace jen pro čtení — dotaz při psaní nesmí přepsat zprávu souběžného uložení (R71)
    ->middleware(['throttle:'.RateLimits::SUGGESTIONS, ReadOnlySession::class])
    ->name('offers.suggestions');

// Prodejny akce pro okno „Kde akce platí“ (R49) — až po otevření okna, ne s každou akcí (R106)
Route::get('/akce/{offer}/prodejny', OfferStoresController::class)
    ->whereNumber('offer')
    ->middleware(['throttle:'.RateLimits::SUGGESTIONS, ReadOnlySession::class])
    ->name('offers.stores');

// Živá ukázka hlídání na úvodní stránce (R90) — dotaz po každé změně výběru, limit jako našeptávač
Route::get('/ukazka-hlidani', WatchDemoController::class)
    ->middleware(['throttle:'.RateLimits::SUGGESTIONS, ReadOnlySession::class])
    ->name('watch-demo');

Route::middleware('auth')->group(function (): void {
    Route::get('/hlidam', [WatchItemController::class, 'index'])->name('watch-items.index');
    Route::post('/hlidam', [WatchItemController::class, 'store'])->name('watch-items.store');
    // Náhled vlastních slov při psaní (R71) — dotaz při každé pauze v psaní, limit jako našeptávač,
    // relace jen pro čtení (souběžné „Přidat“ by jinak přišlo o toast)
    Route::get('/hlidam/nahled', [WatchItemController::class, 'preview'])
        ->middleware(['throttle:'.RateLimits::SUGGESTIONS, ReadOnlySession::class])
        ->name('watch-items.preview');
    Route::put('/hlidam/{watchItem}', [WatchItemController::class, 'update'])->name('watch-items.update');
    Route::delete('/hlidam/{watchItem}', [WatchItemController::class, 'destroy'])->name('watch-items.destroy');

    // Nákupní seznam (R61): přidat / odebrat z karty akce, odškrtnout v obchodě, uklidit po nákupu
    Route::get('/seznam', [ShoppingListController::class, 'index'])->name('shopping-list.index');
    Route::post('/seznam', [ShoppingListController::class, 'toggle'])->name('shopping-list.toggle');
    // Odškrtnutí udělaná bez připojení najednou, až je zase signál (R66)
    Route::patch('/seznam', [ShoppingListController::class, 'sync'])->name('shopping-list.sync');
    Route::delete('/seznam/odskrtnute', [ShoppingListController::class, 'clearChecked'])->name('shopping-list.clear-checked');
    Route::patch('/seznam/{item}', [ShoppingListController::class, 'update'])->whereNumber('item')->name('shopping-list.update');
    Route::delete('/seznam/{item}', [ShoppingListController::class, 'destroy'])->whereNumber('item')->name('shopping-list.destroy');

    // Centrum upozornění (R74): záznamy, detail (cíl upozornění v telefonu), přečtení po zobrazení
    Route::get('/upozorneni', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/upozorneni/precteno', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/upozorneni/{notification}', [NotificationController::class, 'show'])->whereUuid('notification')->name('notifications.show');

    Route::get('/obchody', [ShoppingPreferencesController::class, 'show'])->name('preferences');
    Route::put('/obchody', [ShoppingPreferencesController::class, 'update'])->name('preferences.update');

    Route::get('/ucet', [AccountController::class, 'show'])->name('account');
    Route::delete('/ucet', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::put('/ucet/souhrn', [AccountController::class, 'updateDigest'])->name('account.digest');
    Route::put('/ucet/novinky', [AccountController::class, 'updateMarketing'])->name('account.marketing');
    Route::put('/ucet/moje-slevy', [AccountController::class, 'updateOffersPreferences'])->name('account.offers-preferences');
    Route::delete('/ucet/zarizeni', [AccountController::class, 'logoutOtherDevices'])->name('account.devices.logout');
    // Upozornění v telefonu (R66): zapnout / vypnout na tomto zařízení, zkušební upozornění
    Route::post('/ucet/upozorneni', [PushSubscriptionController::class, 'store'])->name('account.push.store');
    Route::delete('/ucet/upozorneni', [PushSubscriptionController::class, 'destroy'])->name('account.push.destroy');
    Route::post('/ucet/upozorneni/zkouska', [PushSubscriptionController::class, 'test'])->name('account.push.test');
    Route::get('/ucet/obrazek', [AvatarController::class, 'show'])->name('account.avatar');
    Route::post('/ucet/obrazek', [AvatarController::class, 'update'])->name('account.avatar.update');
    Route::delete('/ucet/obrazek', [AvatarController::class, 'destroy'])->name('account.avatar.destroy');

    // Zprávy od nás do centra upozornění posílá admin (R74, etapa 11d)
    Route::middleware('can:send-announcements')->group(function (): void {
        Route::get('/zpravy', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/zpravy', [AnnouncementController::class, 'store'])->name('announcements.store');
    });

    // Přehled uživatelů s poslední aktivitou a nastavením vidí admin (R84)
    Route::middleware('can:view-users')->group(function (): void {
        Route::get('/uzivatele', [UserController::class, 'index'])->name('users.index');
        Route::get('/uzivatele/{user}/obrazek', [UserController::class, 'avatar'])->name('users.avatar');
    });

    // Katalog produktů spravuje admin (R29)
    Route::middleware('can:manage-catalog')->prefix('katalog')->name('catalog.')->group(function (): void {
        Route::get('/', [CatalogController::class, 'index'])->name('index');
        Route::post('/', [CatalogController::class, 'store'])->name('store');
        Route::get('/{product}', [CatalogController::class, 'show'])->name('show');
        Route::put('/{product}', [CatalogController::class, 'update'])->name('update');
        Route::delete('/{product}', [CatalogController::class, 'destroy'])->name('destroy');
        Route::post('/{product}/nabidky/{offer}', [CatalogController::class, 'includeOffer'])->name('offers.include');
        Route::delete('/{product}/nabidky/{offer}', [CatalogController::class, 'excludeOffer'])->name('offers.exclude');
        Route::delete('/{product}/vyrazene/{offer}', [CatalogController::class, 'restoreOffer'])->name('offers.restore');
    });
});
