<?php

/**
 * Aplikace v telefonu (R66): manifest se zkratkami a maskovatelnou ikonou, iPhone (úvodní
 * obrazovky), service worker se seznamem souborů z buildu, stránka bez připojení a spodní
 * lišta záložek. Nová verze po nasazení: verze assetů v service workeru a verze v Můj účet (R78).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Podvržený build (public/build/manifest.json) v dočasné složce — testy nesmí záviset
 * na tom, jestli a jak byl frontend sestavený.
 */
function fakeBuildPublicPath(): string
{
    $path = storage_path('framework/testing/pwa-public');
    File::ensureDirectoryExists($path.'/build');
    File::put($path.'/build/manifest.json', json_encode([
        'resources/js/app.js' => ['file' => 'assets/app-abc12345.js', 'css' => ['assets/app-def67890.css']],
        'node_modules/nunito-latin-wght-normal.woff2' => ['file' => 'assets/nunito-latin-wght-normal-AAAA1111.woff2'],
        'node_modules/nunito-cyrillic-wght-normal.woff2' => ['file' => 'assets/nunito-cyrillic-wght-normal-BBBB2222.woff2'],
    ], JSON_THROW_ON_ERROR));

    return $path;
}

it('manifest má maskovatelnou ikonu a zkratky na hlavní stránky', function (): void {
    $response = $this->get(route('manifest'))
        ->assertOk()
        ->assertJsonPath('id', '/')
        ->assertJsonPath('icons.2.purpose', 'maskable');

    expect(array_column($response->json('shortcuts'), 'url'))->toBe(['/', '/seznam', '/hlidam', '/akce'])
        ->and(array_column($response->json('shortcuts'), 'name'))->toBe(['Moje slevy', 'Seznam', 'Hlídám', 'Všechny akce']);
});

it('odkaz z jiné aplikace otevře spuštěná aplikace z plochy na své adrese, ne na poslední stránce (R132)', function (): void {
    $this->get(route('manifest'))->assertJsonPath('launch_handler.client_mode', ['navigate-existing', 'auto']);
});

it('stránka má meta značky pro iPhone a existující úvodní obrazovky', function (): void {
    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('viewport-fit=cover', escape: false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="Slevohlídka">', escape: false);

    preg_match_all('/rel="apple-touch-startup-image" href="([^"]+)"/', (string) $response->getContent(), $matches);
    expect($matches[1])->toHaveCount(count(config('letaky.pwa.startup_images')));
    foreach ($matches[1] as $image) {
        expect(public_path($image))->toBeFile();
    }
});

it('service worker dostane soubory buildu bez písma, které čeština nepotřebuje', function (): void {
    foreach (['/theme-init.js', '/images/brand/badge-96.png', '/images/brand/icon-192.png', '/images/chains/lidl.svg'] as $file) {
        expect(public_path($file))->toBeFile();
    }
    $this->app->usePublicPath(fakeBuildPublicPath());

    $response = $this->get('/sw.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8')
        ->assertHeader('Cache-Control', 'no-cache, private');

    preg_match('/^self\.SW_CONFIG = (.+);$/m', (string) $response->getContent(), $match);
    $config = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

    expect($config['precache'])->toContain('/build/assets/app-abc12345.js', '/build/assets/app-def67890.css', '/build/assets/nunito-latin-wght-normal-AAAA1111.woff2', '/offline', '/images/chains/lidl.svg')
        ->not->toContain('/build/assets/nunito-cyrillic-wght-normal-BBBB2222.woff2')
        ->and($config['offlinePaths'])->toBe(['/', '/seznam', '/hlidam'])
        ->and($config['version'])->toBeString()
        ->and($response->getContent())->toContain("addEventListener('push'");
});

it('bez sestaveného frontendu service worker není', function (): void {
    $this->app->usePublicPath(storage_path('framework/testing/pwa-missing'));

    $this->get('/sw.js')->assertNotFound();
});

it('stránka bez připojení odkazuje na uložené stránky', function (): void {
    $this->get(route('offline'))
        ->assertOk()
        ->assertSeeText('Jste offline')
        ->assertSee('href="/seznam"', escape: false)
        ->assertSee('noindex', escape: false);
});

it('sdílí adresu service workeru a obnovení po návratu do aplikace', function (): void {
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('pwa.serviceWorkerUrl', '/sw.js')
        ->where('pwa.refreshAfterMinutes', config('letaky.pwa.refresh_after_minutes')));
});

it('přihlášený má všechny hlavní stránky ve spodní liště, katalog admina je v menu pod avatarem (R75)', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->has('navigation', 5)
        ->where('navigation.0.key', 'home')
        ->where('navigation', fn ($items): bool => collect($items)->every(fn (array $item): bool => $item['tab']))
        ->where('auth.catalogUrl', '/katalog'));
});

it('service worker zná verzi assetů stejnou jako Inertia — stránka podle ní pozná nový build (R78)', function (): void {
    $this->app->usePublicPath(fakeBuildPublicPath());

    $response = $this->get('/sw.js')->assertOk();
    preg_match('/^self\.SW_CONFIG = (.+);$/m', (string) $response->getContent(), $match);
    $config = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);

    $expected = hash('xxh128', hash_file('xxh128', public_path('build/manifest.json')).'|'.hash_file('xxh128', lang_path('cs/app.php')));
    expect($config['assetVersion'])->toBe($expected)
        ->and(app(HandleInertiaRequests::class)->version(request()))->toBe($expected)
        ->and($response->getContent())->toContain("'asset-version'");

    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page
        ->where('pwa.updateCheckMinutes', config('letaky.pwa.update_check_minutes')));
});

it('Můj účet ukáže nasazenou verzi z version.txt, lokálně žádnou (R78)', function (): void {
    $this->actingAs(User::factory()->create());
    $path = fakeBuildPublicPath();
    $this->app->usePublicPath($path);
    File::delete($path.'/version.txt');

    $this->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('appVersion', null));

    File::put($path.'/version.txt', "4cf9e35\n");

    $this->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('appVersion', '4cf9e35'));

    File::delete($path.'/version.txt');
});

it('texty UI pošle jen celé načtení stránky, přechod Inertie s nimi v klientu ne (R99)', function (): void {
    $this->get(route('offers'))->assertInertia(fn (Assert $page) => $page->has('translations.app_name'));

    $version = app(HandleInertiaRequests::class)->version(request());
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Except-Once-Props' => 'translations',
    ])->get(route('offers'))->assertOk();

    expect($response->json('props'))->not->toHaveKey('translations')
        ->and($response->json('onceProps.translations.prop'))->toBe('translations');
});
