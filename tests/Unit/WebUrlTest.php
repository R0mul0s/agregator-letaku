<?php

/**
 * Odkazy od obchodů jen jako webové adresy (R67).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use App\Domain\Offers\Parsing\WebUrl;

it('propustí absolutní adresu http(s) s doménou', function (string $url): void {
    expect(WebUrl::orNull($url))->toBe($url);
})->with([
    'https://www.lidl.cz/l/cs/letaky/akcni-letak/view/flyer/page/1',
    'HTTPS://letaky.albert.cz/albert-hyper-40-2026/',
    'http://example.com/a.jpg',
]);

it('jiné schéma, relativní adresu nebo nesmysl zahodí', function (?string $url): void {
    expect(WebUrl::orNull($url))->toBeNull();
})->with([
    'javascript:alert(1)',
    'JaVaScRiPt:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    '//cdn.example.com/a.jpg',
    '/letaky/view',
    'https://',
    null,
]);
