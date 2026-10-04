<?php

/**
 * Odkaz od obchodu (stránka akce, leták, obrázek) jen jako webová adresa (R67). Adresy
 * z API obchodů jdou do href a src na stránce a v e-mailu — „javascript:“ nebo „data:“
 * by zablokovala CSP jen na webu, v e-mailu ne. Obsah od obchodu je nedůvěryhodný vstup.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Offers\Parsing;

final class WebUrl
{
    /** Povolená schémata adres. */
    private const SCHEMES = ['http', 'https'];

    /**
     * Adresa, když je absolutní http(s) s doménou, jinak null.
     */
    public static function orNull(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme) && in_array(strtolower($scheme), self::SCHEMES, true) && is_string($host) && $host !== ''
            ? $url
            : null;
    }
}
