{{--
    Kořenová šablona Inertia aplikace. Hlavička pro vyhledávače a sdílení (R45) se skládá
    na serveru (App\Support\Seo\SeoMeta) — SPA bez SSR ji jinak robotům neukáže.

    @author Roman Hlaváček
    @created 2026-10-02
--}}
@php
    $seo = app(\App\Support\Seo\SeoMeta::class)->forRequest(request());
    // JSON uvnitř <script>: HEX_TAG zabrání tomu, aby text akce ukončil značku („</script>“)
    $jsonLdFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        {{-- Náhled odkazu na sociálních sítích a v chatech (Open Graph, X/Twitter) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ __('app.ui.app_name') }}">
        <meta property="og:locale" content="cs_CZ">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:image" content="{{ $seo['image']['url'] }}">
        <meta property="og:image:width" content="{{ $seo['image']['width'] }}">
        <meta property="og:image:height" content="{{ $seo['image']['height'] }}">
        <meta property="og:image:alt" content="{{ $seo['image']['alt'] }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <meta name="twitter:image" content="{{ $seo['image']['url'] }}">
        {{-- Název aplikace pro šablonu titulku stránek (resources/js/app.js) --}}
        <meta name="application-name" content="{{ __('app.ui.app_name') }}">
        {{-- Barva lišty prohlížeče na mobilu podle režimu systému (= --color-bg v _tokens.scss) --}}
        <meta name="theme-color" content="{{ config('letaky.theme_colors.light') }}" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="{{ config('letaky.theme_colors.dark') }}" media="(prefers-color-scheme: dark)">
        {{-- Ikony z loga Slevohlídky (public/images/brand, vygenerované z slevohlidka-logo.png).
             public/favicon.ico (R53) je icon-32.png v obalu ICO — prohlížeče se na něj ptají samy --}}
        <link rel="icon" href="/images/brand/icon-32.png" sizes="32x32" type="image/png">
        <link rel="icon" href="/images/brand/icon-192.png" sizes="192x192" type="image/png">
        <link rel="apple-touch-icon" href="/images/brand/apple-touch-icon.png">
        {{-- schema.org pro vyhledávače — datový blok, ne skript (CSP ho nespouští) --}}
        @foreach ($seo['jsonLd'] as $data)
            <script type="application/ld+json">{!! json_encode($data, $jsonLdFlags) !!}</script>
        @endforeach
        {{-- Uložený vzhled nastavit před vykreslením, jinak stránka problikne (viz resources/js/lib/theme.js) --}}
        <script src="/theme-init.js"></script>
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
