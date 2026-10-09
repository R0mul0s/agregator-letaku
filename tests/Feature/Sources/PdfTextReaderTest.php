<?php

/**
 * Text s polohou z PDF letáků přes pdftotext -bbox-layout (R86). Program se v testech
 * nespouští — výstup je fixture skutečné strany letáku Lidlu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

use App\Domain\Sources\Exceptions\PdfTextFailed;
use App\Domain\Sources\Pdf\PdfLine;
use App\Domain\Sources\Pdf\PdfTextReader;
use App\Domain\Sources\SourceHttp;
use Illuminate\Http\Client\RequestException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

const PDF_PAGE_FIXTURE = 'pdf/lidl-8-10-2026-page-22.html';

it('převede výstup pdftotext na stránky, řádky a slova s polohou', function (): void {
    $pages = app(PdfTextReader::class)->parse(responseFixture(PDF_PAGE_FIXTURE));

    expect($pages)->toHaveCount(1)
        ->and($pages[0]->number)->toBe(1)
        ->and(round($pages[0]->width))->toBe(601.0);

    $texts = array_map(fn (PdfLine $line): string => $line->text(), $pages[0]->lines);
    expect($texts)->toContain('Plnotučná bryndza', '125 g, 100 g = 20,72 Kč', '25.90');

    // Cena je řádově vyšší písmo než popis s balením
    $price = collect($pages[0]->lines)->first(fn (PdfLine $line): bool => $line->text() === '25.90');
    $details = collect($pages[0]->lines)->first(fn (PdfLine $line): bool => $line->text() === '125 g, 100 g = 20,72 Kč');
    expect($price?->height())->toBeGreaterThan(3 * ($details?->height() ?? INF))
        ->and($price?->xMin)->toBeLessThan(30.0);
});

it('řídicí znaky z glyfů vlastních fontů vynechá místo chyby XML', function (): void {
    $xhtml = str_replace('Plnotučná', "Plnotu\x07čná", responseFixture(PDF_PAGE_FIXTURE));

    $texts = array_map(fn (PdfLine $line): string => $line->text(), app(PdfTextReader::class)->parse($xhtml)[0]->lines);

    expect($texts)->toContain('Plnotučná bryndza');
});

it('PDF stáhne na disk, předá programu pdftotext a soubor pak smaže (R113)', function (): void {
    Http::fake(['https://example.com/letak.pdf' => Http::response('%PDF-test')]);
    $path = null;
    Process::fake(function (PendingProcess $process) use (&$path) {
        $command = (array) $process->command;
        $path = $command[3] ?? null;
        expect($command[0])->toBe('pdftotext')
            ->and(array_slice($command, 1, 2))->toBe(['-bbox-layout', '-q'])
            ->and(is_string($path) && file_get_contents($path) === '%PDF-test')->toBeTrue();

        return Process::result(responseFixture(PDF_PAGE_FIXTURE));
    });

    $pages = app(PdfTextReader::class)->readUrl(new SourceHttp, 'https://example.com/letak.pdf');

    expect($pages)->toHaveCount(1)
        ->and(is_string($path) && file_exists($path))->toBeFalse();
});

it('chybu programu i nečitelný výstup ohlásí výjimkou', function (): void {
    Http::fake(['https://example.com/letak.pdf' => Http::response('%PDF-test')]);
    Process::fake(['*' => Process::result(errorOutput: 'Syntax Error: Couldn\'t read xref table', exitCode: 1)]);
    expect(fn () => app(PdfTextReader::class)->readUrl(new SourceHttp, 'https://example.com/letak.pdf'))->toThrow(PdfTextFailed::class, 'kódem 1');

    expect(fn () => app(PdfTextReader::class)->parse('není to XML'))->toThrow(PdfTextFailed::class, 'není XML');
});

it('odmítnutý požadavek (4xx) neopakuje, chybu serveru ano (R113)', function (): void {
    config(['letaky.http.retries' => 3, 'letaky.http.retry_delay_ms' => 0, 'letaky.http.request_delay_ms' => 0]);
    Http::fake([
        'https://example.com/zakazano' => Http::response('', 401),
        'https://example.com/vypadek' => Http::response('', 503),
    ]);

    expect(fn () => (new SourceHttp)->request()->get('https://example.com/zakazano'))->toThrow(RequestException::class)
        ->and(fn () => (new SourceHttp)->request()->get('https://example.com/vypadek'))->toThrow(RequestException::class);

    Http::assertSentCount(1 + 3);
});
