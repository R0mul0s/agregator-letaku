<?php

/**
 * Manifest webové aplikace (R55, R66) — Slevohlídku jde přidat na plochu telefonu a otevře se
 * bez lišty prohlížeče. Z routy, aby název bral z lang a barvy z konfigurace (stejné jako
 * meta theme-color v app.blade.php). Zkratky po podržení ikony vedou na hlavní stránky;
 * maskovatelná ikona vyplní tvar ikony systému (bez ní Android dá logo do bílého kolečka).
 * Offline režim a upozornění zajišťuje service worker (ServiceWorkerController).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    /** Ikony z loga (public/images/brand) — velikosti, které prohlížeče pro plochu chtějí. */
    private const ICON_SIZES = [192, 512];

    /** Cesta k ikoně dané velikosti. */
    private const ICON_PATH = '/images/brand/icon-%d.png';

    /**
     * Maskovatelná ikona: logo uvnitř bezpečné zóny (80 % průměru) na plném podkladu —
     * systém ji ořízne do kruhu, čtverce nebo kapky.
     */
    private const MASKABLE_ICON = ['src' => '/images/brand/icon-maskable-512.png', 'size' => 512];

    /** Zkratky po podržení ikony: název routy => klíč textu v app.ui.nav. */
    private const SHORTCUTS = [
        'home' => 'home',
        'shopping-list.index' => 'shopping_list',
        'watch-items.index' => 'watch_items',
        'offers' => 'offers',
    ];

    /** Jak dlouho smí manifest ležet v cache prohlížeče (sekundy) — mění se jen s novou verzí. */
    private const CACHE_SECONDS = 86400;

    /**
     * Manifest jako JSON s typem application/manifest+json.
     */
    public function __invoke(): JsonResponse
    {
        $color = config()->string('letaky.theme_colors.light');

        return response()->json([
            // Stálá identita aplikace — kdyby se změnila start_url, telefon ji nebude mít dvakrát
            'id' => '/',
            'name' => __('app.ui.app_name').' — '.__('app.ui.brand.tagline'),
            'short_name' => __('app.ui.app_name'),
            'description' => __('app.seo.pages.home.description'),
            'lang' => 'cs',
            'dir' => 'ltr',
            'start_url' => route('home', absolute: false),
            'scope' => '/',
            'display' => 'standalone',
            'categories' => ['shopping', 'lifestyle'],
            'background_color' => $color,
            'theme_color' => $color,
            'icons' => [
                ...array_map(fn (int $size): array => [
                    'src' => sprintf(self::ICON_PATH, $size),
                    'sizes' => $size.'x'.$size,
                    'type' => 'image/png',
                    'purpose' => 'any',
                ], self::ICON_SIZES),
                [
                    'src' => self::MASKABLE_ICON['src'],
                    'sizes' => self::MASKABLE_ICON['size'].'x'.self::MASKABLE_ICON['size'],
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => array_map(fn (string $routeName, string $labelKey): array => [
                'name' => __('app.ui.nav.'.$labelKey),
                'url' => route($routeName, absolute: false),
            ], array_keys(self::SHORTCUTS), self::SHORTCUTS),
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
