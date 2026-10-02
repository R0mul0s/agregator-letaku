<?php

/**
 * Webové routy aplikace. Přihlášení, registraci a obnovu hesla registruje Fortify (R12).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\HealthImportsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OffersController;
use App\Http\Controllers\OfferSuggestionsController;
use App\Http\Controllers\ShoppingPreferencesController;
use App\Http\Controllers\WatchItemController;
use Illuminate\Support\Facades\Route;

// Cron WebAdminu umí jen zavolat URL (R20, R38) — chráněné tokenem, bez něj 404
Route::get('/cron/import-offers', [CronController::class, 'importOffers'])->name('cron.import-offers');
Route::get('/cron/import-categories', [CronController::class, 'importCategories'])->name('cron.import-categories');

// Monitoring stahování (UptimeRobot) — veřejné, jen stav
Route::get('/health/imports', HealthImportsController::class)->name('health.imports');

Route::middleware('auth')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/akce', OffersController::class)->name('offers');
    Route::get('/akce/naseptavac', OfferSuggestionsController::class)->name('offers.suggestions');

    Route::get('/hlidam', [WatchItemController::class, 'index'])->name('watch-items.index');
    Route::post('/hlidam', [WatchItemController::class, 'store'])->name('watch-items.store');
    Route::put('/hlidam/{watchItem}', [WatchItemController::class, 'update'])->name('watch-items.update');
    Route::delete('/hlidam/{watchItem}', [WatchItemController::class, 'destroy'])->name('watch-items.destroy');

    Route::get('/obchody', [ShoppingPreferencesController::class, 'show'])->name('preferences');
    Route::put('/obchody', [ShoppingPreferencesController::class, 'update'])->name('preferences.update');

    Route::get('/ucet', [AccountController::class, 'show'])->name('account');
    Route::delete('/ucet', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::put('/ucet/moje-slevy', [AccountController::class, 'updateOffersPreferences'])->name('account.offers-preferences');
    Route::delete('/ucet/zarizeni', [AccountController::class, 'logoutOtherDevices'])->name('account.devices.logout');
    Route::get('/ucet/obrazek', [AvatarController::class, 'show'])->name('account.avatar');
    Route::post('/ucet/obrazek', [AvatarController::class, 'update'])->name('account.avatar.update');
    Route::delete('/ucet/obrazek', [AvatarController::class, 'destroy'])->name('account.avatar.destroy');

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
