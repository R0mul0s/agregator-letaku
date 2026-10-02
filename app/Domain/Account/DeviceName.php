<?php

/**
 * Čitelný název zařízení z User-Agentu pro seznam přihlášení v účtu (R40): „Edge · Windows“.
 * Jen orientačně — stačí rozlišit vlastní zařízení, přesná detekce není potřeba.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Account;

final class DeviceName
{
    /**
     * Prohlížeče: vzor => název. Pořadí je důležité — Edge a Opera se hlásí i jako Chrome,
     * Chrome i jako Safari.
     */
    private const BROWSERS = [
        '/Edg(e|A|iOS)?\//' => 'Edge',
        '/OPR\/|Opera/' => 'Opera',
        '/SamsungBrowser\//' => 'Samsung Internet',
        '/Firefox\/|FxiOS\//' => 'Firefox',
        '/Chrome\/|CriOS\//' => 'Chrome',
        '/Safari\//' => 'Safari',
    ];

    /** Systémy: vzor => název. iOS a Android dřív než macOS a Linux, za které se také vydávají. */
    private const SYSTEMS = [
        '/iPhone|iPad|iPod/' => 'iOS',
        '/Android/' => 'Android',
        '/Windows/' => 'Windows',
        '/Mac OS X|Macintosh/' => 'macOS',
        '/CrOS/' => 'ChromeOS',
        '/Linux/' => 'Linux',
    ];

    /** Oddělovač prohlížeče a systému v názvu. */
    private const SEPARATOR = ' · ';

    /**
     * Název zařízení; neznámý User-Agent dá text „Neznámé zařízení“.
     */
    public static function fromUserAgent(?string $userAgent): string
    {
        $parts = array_filter([
            self::firstMatch(self::BROWSERS, (string) $userAgent),
            self::firstMatch(self::SYSTEMS, (string) $userAgent),
        ]);

        return $parts === [] ? __('app.ui.account.unknown_device') : implode(self::SEPARATOR, $parts);
    }

    /**
     * Název prvního vzoru, který v textu najde shodu.
     *
     * @param  array<string, string>  $patterns
     */
    private static function firstMatch(array $patterns, string $text): ?string
    {
        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $text) === 1) {
                return $name;
            }
        }

        return null;
    }
}
