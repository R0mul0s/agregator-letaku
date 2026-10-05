<?php

/**
 * Právní stránky, patička a české chybové stránky (R51).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

use App\Http\Responses\ErrorToast;
use App\Support\Legal\LegalDocuments;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

it('ukáže právní stránku s údaji provozovatele z konfigurace', function (string $route, string $title): void {
    config(['letaky.operator' => [
        'name' => 'Jan Novák',
        'company_id' => '12345678',
        'address' => ['Hlavní 1', '110 00 Praha'],
        'email' => 'info@example.com',
    ]]);

    $this->get(route($route))
        ->assertOk()
        ->assertSee('<meta name="robots" content="index, follow">', escape: false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Legal')
            ->where('title', $title)
            ->where('html', fn (string $html): bool => str_contains($html, 'Jan Novák, IČO 12345678, se sídlem Hlavní 1, 110 00 Praha')
                && str_contains($html, 'info@example.com')
                && ! str_contains($html, '{operator}')
                && ! str_contains($html, '<!--')));
})->with([
    'podmínky' => ['legal.terms', 'Podmínky užití'],
    'zásady' => ['legal.privacy', 'Zásady zpracování osobních údajů'],
]);

it('chybějící údaj provozovatele na právní stránce označí k doplnění', function (): void {
    config(['letaky.operator.company_id' => null]);

    $this->get(route('legal.privacy'))
        ->assertInertia(fn (Assert $page) => $page->where('html', fn (string $html): bool => str_contains($html, 'IČO [doplnit]')));
});

it('sdílí patičku s kontaktem — značka, sídlo po řádcích (bez prázdných) a odkazy (R72)', function (): void {
    config(['letaky.operator.address' => ['Hlavní 1', ' ', '110 00 Praha']]);

    $this->get(route('offers'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteFooter.brand', 'RHsoft.cz')
            ->where('siteFooter.contactUrl', '/kontakt')
            ->where('siteFooter.addressLines', ['Hlavní 1', '110 00 Praha'])
            ->missing('siteFooter.companyId')
            ->where('siteFooter.termsUrl', '/podminky')
            ->where('siteFooter.privacyUrl', '/ochrana-udaju'));
});

it('má právní stránky v sitemap', function (): void {
    $this->get(route('sitemap'))
        ->assertSee(route('legal.terms'))
        ->assertSee(route('legal.privacy'));
});

it('neexistující stránku ukáže česky', function (): void {
    $this->get('/tahle-stranka-neni')
        ->assertNotFound()
        ->assertSee(__('app.errors.404.title'))
        ->assertSee(__('app.errors.home'));
});

it('vypršelou relaci a limit požadavků u Inertie vrátí zpět s toastem, ostatní chyby nechá', function (): void {
    $inertia = Request::create('/ucet', 'PUT', server: ['HTTP_X_INERTIA' => 'true']);

    $expired = ErrorToast::respond(response('', 419), $inertia);
    expect($expired->getStatusCode())->toBe(302)
        ->and(session('status'))->toBe('session-expired');

    expect(ErrorToast::respond(response('', 429), $inertia)->getStatusCode())->toBe(302)
        ->and(session('status'))->toBe('too-many-requests');
    expect(ErrorToast::respond(response('', 404), $inertia)->getStatusCode())->toBe(404);
    expect(ErrorToast::respond(response('', 419), Request::create('/ucet', 'PUT'))->getStatusCode())->toBe(419);
});

it('sdílí nastavení souhlasu s cookies; ID měření Google Analytics jen na produkci (R52)', function (): void {
    config(['letaky.cookie_consent.google_measurement_id' => 'G-TEST123']);

    $this->get(route('offers'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cookieConsent.measurementId', null)
            ->where('cookieConsent.version', config('letaky.cookie_consent.version'))
            // Odkaz z cookie lišty vede rovnou na kapitolu o cookies (R69)
            ->where('cookieConsent.privacyUrl', '/ochrana-udaju#'.LegalDocuments::COOKIES_SECTION)
            ->where('cookieConsent.redactedPaths', ['/nove-heslo', '/overeni-emailu', '/odhlaseni']));

    $this->app['env'] = 'production';
    $this->get(route('offers'))
        ->assertInertia(fn (Assert $page) => $page->where('cookieConsent.measurementId', 'G-TEST123'));
});

it('z kapitol dokumentu sestaví obsah a nadpisům dá id pro odkazy', function (): void {
    $this->get(route('legal.privacy'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.0', ['id' => '1-kdo-vase-udaje-zpracovava', 'title' => '1. Kdo vaše údaje zpracovává'])
            // Cíl odkazu z cookie lišty (R69) — přejmenování nadpisu by ho rozbilo
            ->where('sections.4.id', LegalDocuments::COOKIES_SECTION)
            ->where('html', fn (string $html): bool => str_contains($html, '<h2 id="1-kdo-vase-udaje-zpracovava">1. Kdo vaše údaje zpracovává</h2>')));
});
