<?php

/**
 * HTTP klient zdrojů stahuje jen z veřejných adres přes https (R170) — adresy letáků přicházejí
 * i z dat obchodu a podvržená by vedla na vnitřní síť hostingu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Sources\Exceptions\SourceUrlRejected;
use App\Domain\Sources\SourceHttp;
use Illuminate\Support\Facades\Http;

it('stáhne z https adresy s doménou', function (): void {
    Http::fake(['https://www.coopclub.cz/*' => Http::response('ok')]);

    expect((new SourceHttp)->request(0)->get('https://www.coopclub.cz/letaky/')->body())->toBe('ok');
});

it('odmítne adresu bez https, s IP adresou nebo localhost a neopakuje ji', function (string $url): void {
    Http::fake();

    expect(fn () => (new SourceHttp)->request(0)->get($url))->toThrow(SourceUrlRejected::class);
    Http::assertNothingSent();
})->with([
    'http' => 'http://www.coopclub.cz/letaky/',
    'file' => 'file:///etc/passwd',
    'IPv4' => 'https://169.254.169.254/latest/meta-data/',
    'IPv6' => 'https://[::1]/admin',
    'localhost' => 'https://localhost/admin',
]);

it('odmítne nebezpečnou adresu i v souběžných požadavcích', function (): void {
    Http::fake();

    expect(fn () => (new SourceHttp)->pool(['a' => ['https://127.0.0.1/', []]], 1, 0))->toThrow(SourceUrlRejected::class);
});
