<?php

/**
 * Manifest webové aplikace (R55) — Slevohlídku jde přidat na plochu telefonu a otevře se
 * bez lišty prohlížeče. Z routy, aby název bral z lang a barvy z konfigurace (stejné jako
 * meta theme-color v app.blade.php). Bez service workeru — aplikace offline nefunguje.
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

    /** Jak dlouho smí manifest ležet v cache prohlížeče (sekundy) — mění se jen s novou verzí. */
    private const CACHE_SECONDS = 86400;

    /**
     * Manifest jako JSON s typem application/manifest+json.
     */
    public function __invoke(): JsonResponse
    {
        $color = config()->string('letaky.theme_colors.light');

        return response()->json([
            'name' => __('app.ui.app_name').' — '.__('app.ui.brand.tagline'),
            'short_name' => __('app.ui.app_name'),
            'description' => __('app.seo.pages.home.description'),
            'lang' => 'cs',
            'start_url' => route('home', absolute: false),
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => $color,
            'theme_color' => $color,
            'icons' => array_map(fn (int $size): array => [
                'src' => sprintf(self::ICON_PATH, $size),
                'sizes' => $size.'x'.$size,
                'type' => 'image/png',
            ], self::ICON_SIZES),
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
