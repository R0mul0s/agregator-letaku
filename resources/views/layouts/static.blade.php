{{--
    Statická stránka bez Inertie — hlavička s logem a obsah. Pro chybové stránky (R51), kde
    neběží session ani sdílená data Inertie, a pro stránku „Jste offline“ (R66), kterou service
    worker ukáže bez připojení. Sekce: title, content.

    @author Roman Hlaváček
    @created 2026-10-04
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="{{ config('letaky.theme_colors.light') }}" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="{{ config('letaky.theme_colors.dark') }}" media="(prefers-color-scheme: dark)">
        <title>@yield('title') · {{ __('app.ui.app_name') }}</title>
        <link rel="icon" href="/images/brand/icon-32.png" sizes="32x32" type="image/png">
        <script src="/theme-init.js"></script>
        @vite(['resources/scss/app.scss'])
    </head>
    <body>
        <header class="app-header app-header--guest">
            <div class="app-header__inner">
                <a href="{{ route('home') }}" class="app-header__brand">
                    <img src="/images/brand/logo-mark-128.webp" width="128" height="128" alt="" class="app-header__logo">
                    <span class="app-header__name">
                        <span class="app-header__wordmark"><span class="app-header__wordmark-first">{{ __('app.ui.brand.first') }}</span>{{ __('app.ui.brand.second') }}</span>
                        <span class="app-header__tagline">{{ __('app.ui.brand.tagline') }}</span>
                    </span>
                </a>
            </div>
        </header>
        <main class="page">
            <header class="page__header">
                <h1 class="page__title">@yield('title')</h1>
            </header>
            @yield('content')
        </main>
    </body>
</html>
