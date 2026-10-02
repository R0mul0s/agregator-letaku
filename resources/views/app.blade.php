{{--
    Kořenová šablona Inertia aplikace.

    @author Roman Hlaváček
    @created 2026-10-02
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ __('app.meta.description') }}">
        {{-- Název aplikace pro šablonu titulku stránek (resources/js/app.js) --}}
        <meta name="application-name" content="{{ __('app.ui.app_name') }}">
        {{-- Barva lišty prohlížeče na mobilu podle režimu systému (= --color-bg v _tokens.scss) --}}
        <meta name="theme-color" content="{{ config('letaky.theme_colors.light') }}" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="{{ config('letaky.theme_colors.dark') }}" media="(prefers-color-scheme: dark)">
        <title inertia>{{ __('app.ui.app_name') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        {{-- Uložený vzhled nastavit před vykreslením, jinak stránka problikne (viz resources/js/lib/theme.js) --}}
        <script src="/theme-init.js"></script>
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
