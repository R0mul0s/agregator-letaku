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
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/ucet', AccountController::class)->name('account');
});
