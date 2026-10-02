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
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OffersController;
use App\Http\Controllers\ShoppingPreferencesController;
use App\Http\Controllers\WatchItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/akce', OffersController::class)->name('offers');

    Route::get('/hlidam', [WatchItemController::class, 'index'])->name('watch-items.index');
    Route::post('/hlidam', [WatchItemController::class, 'store'])->name('watch-items.store');
    Route::put('/hlidam/{watchItem}', [WatchItemController::class, 'update'])->name('watch-items.update');
    Route::delete('/hlidam/{watchItem}', [WatchItemController::class, 'destroy'])->name('watch-items.destroy');

    Route::get('/obchody', [ShoppingPreferencesController::class, 'show'])->name('preferences');
    Route::put('/obchody', [ShoppingPreferencesController::class, 'update'])->name('preferences.update');

    Route::get('/ucet', AccountController::class)->name('account');
});
