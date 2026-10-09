<?php

/**
 * Menší varianta fotek z CDN commercetools — Billa a Penny (R108).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

use App\Domain\Offers\Parsing\CommercetoolsImage;

const COMMERCETOOLS_IMAGE = 'https://images.cdn.europe-west1.gcp.commercetools.com/1df039f1-4705-4f79-aa90-cf907a6ec063/82-334942-0277015113-CsWrIm-I';

it('vloží variantu medium před koncovku', function (): void {
    expect(CommercetoolsImage::resized(COMMERCETOOLS_IMAGE.'.jpg'))->toBe(COMMERCETOOLS_IMAGE.'-medium.jpg')
        ->and(CommercetoolsImage::resized(COMMERCETOOLS_IMAGE.'.PNG'))->toBe(COMMERCETOOLS_IMAGE.'-medium.PNG');
});

it('jinou adresu nechá beze změny', function (string $url): void {
    expect(CommercetoolsImage::resized($url))->toBe($url);
})->with([
    'už s variantou' => COMMERCETOOLS_IMAGE.'-small.jpg',
    'jiné CDN' => 'https://kaufland.media.schwarz/is/image/schwarz/2869700000000_CZ_P.jpg',
    's parametry' => COMMERCETOOLS_IMAGE.'.jpg?w=200',
    'bez koncovky' => COMMERCETOOLS_IMAGE,
    'nesmysl' => 'not a url',
]);

it('vezme první fotku produktu z API', function (): void {
    expect(CommercetoolsImage::fromProduct(['images' => [COMMERCETOOLS_IMAGE.'.jpg', 'https://example.com/b.jpg']]))->toBe(COMMERCETOOLS_IMAGE.'-medium.jpg')
        ->and(CommercetoolsImage::fromProduct(['images' => []]))->toBeNull()
        ->and(CommercetoolsImage::fromProduct(['images' => [42]]))->toBeNull()
        ->and(CommercetoolsImage::fromProduct([]))->toBeNull();
});
