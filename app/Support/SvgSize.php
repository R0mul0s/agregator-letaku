<?php

/**
 * Poměr stran obrázku SVG z kořenového prvku (`width` / `height`, jinak `viewBox`) — pro atributy
 * width a height u <img> (R123). Bez nich má obrázek před načtením šířku 0 a po načtení posune
 * okolní obsah (layout shift). Výsledek je v cache podle času změny souboru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class SvgSize
{
    /** Výška, ke které se šířka přepočítá — atributy nesou jen poměr, velikost řídí CSS. */
    private const REFERENCE_HEIGHT = 100;

    /** Rozměr v atributu: číslo, případně v px (jiné jednotky poměr neurčí). */
    private const LENGTH_PATTERN = '/^\s*([0-9]*\.?[0-9]+)\s*(px)?\s*$/';

    /**
     * Šířka a výška v poměru stran obrázku (výška REFERENCE_HEIGHT), null = soubor nejde přečíst
     * nebo nemá rozměry.
     *
     * @return array{width: int, height: int}|null
     */
    public static function of(string $path): ?array
    {
        $modified = @filemtime($path);
        if ($modified === false) {
            return null;
        }

        return Cache::rememberForever('svg-size.'.md5($path).'.'.$modified, fn (): ?array => self::read($path));
    }

    /**
     * Přečte rozměry z kořenového <svg>.
     *
     * @return array{width: int, height: int}|null
     */
    private static function read(string $path): ?array
    {
        $content = @file_get_contents($path);
        if ($content === false || ! preg_match('/<svg\b[^>]*>/s', $content, $match)) {
            return null;
        }
        $root = $match[0];

        $width = self::length(self::attribute($root, 'width'));
        $height = self::length(self::attribute($root, 'height'));
        if ($width === null || $height === null) {
            $viewBox = preg_split('/[\s,]+/', trim(self::attribute($root, 'viewBox') ?? ''));
            [$width, $height] = is_array($viewBox) && count($viewBox) === 4 ? [(float) $viewBox[2], (float) $viewBox[3]] : [null, null];
        }
        if (! $width || ! $height) {
            return null;
        }

        return ['width' => (int) round($width / $height * self::REFERENCE_HEIGHT), 'height' => self::REFERENCE_HEIGHT];
    }

    /**
     * Hodnota atributu kořenového prvku, null = chybí.
     */
    private static function attribute(string $root, string $name): ?string
    {
        return preg_match('/\s'.$name.'\s*=\s*"([^"]*)"/', $root, $match) ? $match[1] : null;
    }

    /**
     * Délka z atributu v px, null = chybí nebo je v jiné jednotce (%, em).
     */
    private static function length(?string $value): ?float
    {
        return $value !== null && preg_match(self::LENGTH_PATTERN, $value, $match) ? (float) $match[1] : null;
    }
}
