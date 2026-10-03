{{--
    Česká chybová stránka (R51) pro běžné načtení stránky — 404, 403, 419, 429, 500, 503…
    Jen Blade, ne Inertia: při chybě mimo routy neběží session ani sdílená data Inertie.
    Text podle kódu z lang/cs/app.php (errors), jinak obecný pro 4xx / 5xx.

    @author Roman Hlaváček
    @created 2026-10-03
--}}
@php
    $status = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $exception->getStatusCode() : 500;
    $key = trans()->has('app.errors.'.$status.'.title') ? (string) $status : ($status >= 500 ? '5xx' : '4xx');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('app.errors.'.$key.'.title') }} · {{ __('app.ui.app_name') }}</title>
        <link rel="icon" href="/images/brand/icon-32.png" sizes="32x32" type="image/png">
        <script src="/theme-init.js"></script>
        @vite(['resources/scss/app.scss'])
    </head>
    <body>
        <header class="app-header app-header--guest">
            <div class="app-header__inner">
                <a href="{{ route('home') }}" class="app-header__brand">
                    <img src="/images/brand/logo-mark.png" alt="" class="app-header__logo">
                    <span class="app-header__name">
                        <span class="app-header__wordmark"><span class="app-header__wordmark-first">{{ __('app.ui.brand.first') }}</span>{{ __('app.ui.brand.second') }}</span>
                        <span class="app-header__tagline">{{ __('app.ui.brand.tagline') }}</span>
                    </span>
                </a>
            </div>
        </header>
        <main class="page">
            <header class="page__header">
                <h1 class="page__title">{{ __('app.errors.'.$key.'.title') }}</h1>
            </header>
            <div class="empty-state">
                <img src="/images/brand/icon-192.png" alt="" class="empty-state__mascot">
                <p class="empty-state__text">{{ __('app.errors.'.$key.'.text') }}</p>
                <div class="empty-state__action">
                    <a href="{{ route('home') }}" class="button button--primary">{{ __('app.errors.home') }}</a>
                </div>
            </div>
        </main>
    </body>
</html>
