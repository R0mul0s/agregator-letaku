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
        {{-- viewport-fit=cover: aplikace z plochy jde až k výřezu displeje, odsazení řeší env(safe-area-inset-*) (R66) --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title inertia>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        {{-- Ověření webu v Seznam Webmasteru (letaky.site_verification) --}}
        @if (filled(config('letaky.site_verification.seznam')))
            <meta name="seznam-wmt" content="{{ config('letaky.site_verification.seznam') }}">
        @endif
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
        <meta name="twitter:image:alt" content="{{ $seo['image']['alt'] }}">
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
        {{-- Přidání na plochu telefonu (R55, ManifestController) --}}
        <link rel="manifest" href="{{ route('manifest', absolute: false) }}">
        {{-- iPhone (R66): okno bez lišty Safari, název pod ikonou, stavový řádek nad stránkou (ne přes ni)
             a úvodní obrazovka podle rozlišení displeje — iOS ji z manifestu nebere --}}
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ __('app.ui.app_name') }}">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        @foreach (config('letaky.pwa.startup_images') as [$width, $height, $ratio])
            <link rel="apple-touch-startup-image" href="/images/brand/splash-{{ $width }}x{{ $height }}x{{ $ratio }}.png"
                media="(device-width: {{ $width }}px) and (device-height: {{ $height }}px) and (-webkit-device-pixel-ratio: {{ $ratio }}) and (orientation: portrait)">
        @endforeach
        {{-- schema.org pro vyhledávače — datový blok, ne skript (CSP ho nespouští) --}}
        @foreach ($seo['jsonLd'] as $data)
            <script type="application/ld+json">{!! json_encode($data, $jsonLdFlags) !!}</script>
        @endforeach
        {{-- Uložený vzhled a třídu has-js nastavit před vykreslením, jinak stránka problikne (viz resources/js/lib/theme.js) --}}
        {{-- Vložený, ne soubor — samostatný požadavek blokoval první vykreslení (PageSpeed). CSP ho pouští
             jen podle otisku SHA-256 v public/.htaccess (test hlídá, že sedí s public/theme-init.js) --}}
        @php($themeInit = public_path('theme-init.js'))
        @if (is_file($themeInit))
            <script>{!! trim((string) file_get_contents($themeInit)) !!}</script>
        @else
            <script src="/theme-init.js"></script>
        @endif
        {{-- Kód aktuální stránky (a co importuje) přednačíst hned s HTML — jinak ho prohlížeč objeví až
             po spuštění app.js a první vykreslení čeká o kolo síťových požadavků déle --}}
        @vite(['resources/scss/app.scss', 'resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body>
        {{-- Obsah pro roboty bez JavaScriptu (R94) — app.js ho po spuštění aplikace odstraní --}}
        @include('seo.content')
        @inertia
    </body>
</html>
