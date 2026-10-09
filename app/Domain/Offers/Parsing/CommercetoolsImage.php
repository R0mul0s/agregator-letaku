<?php

/**
 * Fotka produktu z CDN commercetools (Billa, Penny) v menší variantě (R108). API posílá
 * originál (až ~350 kB, 800 px), CDN nabízí varianty příponou před koncovkou souboru
 * („…-CsWrIm-I-medium.jpg“): thumb 50 px, small 150 px, medium 400 px, large 700 px,
 * zoom 1500 px (delší strana). Karta akce ukazuje fotku vysokou 8rem — medium stačí
 * i na displeji s hustotou 3× a je asi desetkrát menší.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Offers\Parsing;

final class CommercetoolsImage
{
    /** Varianta pro karty akcí a našeptávač. */
    private const VARIANT = 'medium';

    /** Varianty, které CDN zná — adresu, která už nějakou nese, nechat. */
    private const VARIANTS = ['thumb', 'small', 'medium', 'large', 'zoom'];

    /** CDN commercetools v libovolném regionu Google Cloud. */
    private const HOST_PATTERN = '/^images\.cdn\.[a-z0-9-]+\.gcp\.commercetools\.com$/i';

    /** Koncovka obrázku na konci cesty. */
    private const EXTENSION_PATTERN = '/(\.(?:jpe?g|png|webp|gif))$/i';

    /**
     * První fotka produktu z odpovědi API (`images[0]`) v menší variantě; null bez fotky.
     *
     * @param  array<mixed>  $product
     */
    public static function fromProduct(array $product): ?string
    {
        $images = is_array($product['images'] ?? null) ? $product['images'] : [];
        $url = $images[0] ?? null;

        return is_string($url) ? self::resized($url) : null;
    }

    /**
     * Adresa varianty VARIANT; jinou adresu (jiné CDN, s parametry, bez koncovky
     * nebo už s variantou) vrátí beze změny.
     */
    public static function resized(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($host) || preg_match(self::HOST_PATTERN, $host) !== 1
            || ! is_string($path) || ! str_ends_with($url, $path)
            || preg_match(self::EXTENSION_PATTERN, $path, $match) !== 1) {
            return $url;
        }

        $stem = substr($url, 0, -strlen($match[1]));
        foreach (self::VARIANTS as $variant) {
            if (str_ends_with($stem, '-'.$variant)) {
                return $url;
            }
        }

        return $stem.'-'.self::VARIANT.$match[1];
    }
}
