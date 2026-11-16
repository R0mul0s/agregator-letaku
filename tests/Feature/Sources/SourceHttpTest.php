<?php

/**
 * HTTP klient zdrojů stahuje jen z veřejných adres přes https (R170) — adresy letáků přicházejí
 * i z dat obchodu a podvržená by vedla na vnitřní síť hostingu. Po odpovědi 429 opakuje až v čase,
 * který server uvede (R184).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Domain\Sources\Exceptions\SourceFileTooLarge;
use App\Domain\Sources\Exceptions\SourceUrlRejected;
use App\Domain\Sources\HostResolver;
use App\Domain\Sources\SourceHttp;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestHostResolver;

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

it('odmítne jméno, které se přeloží na neveřejnou adresu, i zkrácený zápis IP (R186)', function (string $host, string $address): void {
    app()->instance(HostResolver::class, new TestHostResolver([$host => [TestHostResolver::PUBLIC_ADDRESS, $address]]));
    Http::fake();

    expect(fn () => (new SourceHttp)->request(0)->get("https://{$host}/letak.pdf"))->toThrow(SourceUrlRejected::class);
    Http::assertNothingSent();
})->with([
    'zkrácený zápis 127.1' => ['127.1', '127.0.0.1'],
    'číslo místo IP' => ['2130706433', '127.0.0.1'],
    'localhost s tečkou' => ['localhost.', '127.0.0.1'],
    'doména do vnitřní sítě' => ['letaky.zly.example', '10.0.0.5'],
    'metadata cloudu' => ['meta.zly.example', '169.254.169.254'],
    'CGNAT' => ['cgnat.zly.example', '100.64.0.1'],
    'IPv6 lokální' => ['v6.zly.example', 'fd00::1'],
]);

it('odmítne přesměrování na jméno s neveřejnou adresou (R186)', function (): void {
    app()->instance(HostResolver::class, new TestHostResolver(['vnitrni.zly.example' => ['192.168.1.1']]));
    Sleep::fake();
    Http::fake([
        'https://www.coopclub.cz/*' => Http::response('', 302, ['Location' => 'https://vnitrni.zly.example/']),
        '*' => Http::response('tajné'),
    ]);

    expect(fn () => (new SourceHttp)->request(0)->get('https://www.coopclub.cz/letaky/'))->toThrow(SourceUrlRejected::class);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'vnitrni.zly.example'));
    // Odmítnutá adresa se neopakuje
    Sleep::assertNeverSlept();
});

it('soubor nad limit velikosti odmítne, neopakuje a nenechá ho projít (R186)', function (): void {
    config(['letaky.http.download_max_bytes' => 5, 'letaky.http.retries' => 3, 'letaky.http.retry_delay_ms' => 0]);
    Http::fake(['https://example.com/letak.pdf' => Http::response('%PDF-test')]);
    $path = (string) tempnam(sys_get_temp_dir(), 'test');

    try {
        expect(fn () => (new SourceHttp)->download('https://example.com/letak.pdf', $path, 0))->toThrow(SourceFileTooLarge::class);
        Http::assertSentCount(1);
    } finally {
        @unlink($path);
    }
});

it('odmítne nebezpečnou adresu i v souběžných požadavcích', function (): void {
    Http::fake();

    expect(fn () => (new SourceHttp)->pool(['a' => ['https://127.0.0.1/', []]], 1, 0))->toThrow(SourceUrlRejected::class);
});

/** Odpověď API Globusu po překročení kvóty (15. 11. 2026, zkrácená). */
const GLOBUS_QUOTA_BODY = '{"error":"You have exceeded your quota .You can access API after 2026-Nov-15 04:51:00+0000 UTC","messages":["Client error"]}';

it('po 429 Globusu počká do času z odpovědi s rezervou a pak stáhne (R184)', function (): void {
    Sleep::fake();
    $this->travelTo('2026-11-15 04:50:40 UTC');
    Http::fake(['https://www.globus.cz/*' => Http::sequence()->push(GLOBUS_QUOTA_BODY, 429)->push('ok')]);

    expect((new SourceHttp)->request(0)->get('https://www.globus.cz/api/v1/gsoa/actionOffers/houses/4005/')->body())->toBe('ok');
    // 20 s do 04:51:00 a 2 s rezerva — ne 2 s pevné pauzy, které by narazily na stejnou minutu kvóty
    Sleep::assertSequence([Sleep::for(22000)->milliseconds()]);
});

it('po 429 s hlavičkou Retry-After počká uvedený počet sekund, nejdéle horní mez (R184)', function (string $retryAfter, int $sleepMs): void {
    Sleep::fake();
    $this->freezeTime();
    Http::fake(['https://www.coopclub.cz/*' => Http::sequence()->push('', 429, ['Retry-After' => $retryAfter])->push('ok')]);

    expect((new SourceHttp)->request(0)->get('https://www.coopclub.cz/letaky/')->body())->toBe('ok');
    Sleep::assertSequence([Sleep::for($sleepMs)->milliseconds()]);
})->with([
    '30 s' => ['30', 32000],
    'přes horní mez' => ['3600', 70000],
]);

it('po 429 bez času počká pevnou delší pauzu, po chybě serveru jen krátkou (R184)', function (int $status, int $sleepMs): void {
    Sleep::fake();
    Http::fake(['https://www.coopclub.cz/*' => Http::sequence()->push('', $status)->push('ok')]);

    expect((new SourceHttp)->request(0)->get('https://www.coopclub.cz/letaky/')->body())->toBe('ok');
    Sleep::assertSequence([Sleep::for($sleepMs)->milliseconds()]);
})->with([
    '429 bez času' => [429, 20000],
    '503' => [503, 2000],
]);

it('po 429 zopakuje i požadavek ze souběžné dávky (katalogy Globusu, R184)', function (): void {
    $this->travelTo('2026-11-15 04:50:40 UTC');
    Http::fake(['https://www.globus.cz/*' => Http::sequence()->push(GLOBUS_QUOTA_BODY, 429)->push('ok')]);

    $responses = (new SourceHttp)->pool(['4005' => ['https://www.globus.cz/api/v1/gsoa/actionOffers/houses/4005/', []]], 1, 0);

    expect($responses['4005']->body())->toBe('ok');
    Http::assertSentCount(2);
});
