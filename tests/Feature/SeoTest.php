<?php

/**
 * Hlavička pro vyhledávače a sdílení, soubory pro roboty a omezení počtu požadavků (R45).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Enums\Chain;
use App\Models\Offer;
use App\Models\User;

beforeEach(function (): void {
    $this->travelTo('2026-10-02 10:00:00');
});

/**
 * Obsah meta značky z HTML stránky (name nebo property), nebo null.
 */
function metaContent(string $html, string $name): ?string
{
    $pattern = '/<meta (?:name|property)="'.preg_quote($name, '/').'" content="([^"]*)"/';

    return preg_match($pattern, $html, $match) === 1 ? html_entity_decode($match[1]) : null;
}

it('úvodní stránka má titulek, popis, canonical, Open Graph a schema.org', function (): void {
    $html = $this->get('/')->assertOk()->getContent();

    expect(metaContent($html, 'robots'))->toBe('index, follow')
        ->and(metaContent($html, 'og:title'))->toContain('Slevohlídka')
        ->and(metaContent($html, 'og:image'))->toEndWith('/images/brand/og-image.png')
        ->and(metaContent($html, 'twitter:card'))->toBe('summary_large_image')
        ->and($html)->toContain('<link rel="canonical" href="'.url('/').'/">')
        ->and($html)->toContain('"@type":"WebSite"')
        ->and($html)->toContain('"urlTemplate":"'.route('offers').'?q={search_term_string}"');
});

it('Všechny akce: obchod a stránka v canonical, rozsah a hledání ne; hledání se neindexuje', function (): void {
    $html = $this->get('/akce?chain=kaufland&od=1&strana=2')->getContent();
    expect($html)->toContain('<link rel="canonical" href="'.route('offers').'?chain=kaufland&amp;strana=2">')
        ->and(metaContent($html, 'robots'))->toBe('index, follow')
        ->and(metaContent($html, 'og:title'))->toContain('Kaufland');

    $search = $this->get('/akce?q=vejce')->getContent();
    expect(metaContent($search, 'robots'))->toBe('noindex, follow')
        ->and($search)->not->toContain('application/ld+json');
});

it('přihlášení se neindexuje, stránky za přihlášením ani nesledují', function (): void {
    expect(metaContent($this->get('/login')->getContent(), 'robots'))->toBe('noindex, follow');

    $html = $this->actingAs(User::factory()->create())->get('/hlidam')->getContent();
    expect(metaContent($html, 'robots'))->toBe('noindex, nofollow');
});

it('za proxy bere https a IP klienta z X-Forwarded hlaviček', function (): void {
    $html = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.7'])->get('/')->getContent();

    expect($html)->toContain('<link rel="canonical" href="https://');
});

it('robots.txt mimo produkci zakáže vše, na produkci soukromé cesty a odkáže na sitemap', function (): void {
    $this->get('/robots.txt')->assertOk()->assertSeeText('Disallow: /');

    $this->app['env'] = 'production';
    $this->get('/robots.txt')
        ->assertSeeText('Allow: /')
        ->assertSeeText('Disallow: /hlidam')
        ->assertDontSeeText('Disallow: /login')
        ->assertSeeText('Sitemap: '.route('sitemap'));
});

it('sitemap.xml a llms.txt obsahují jen obchody s aktuálními akcemi', function (): void {
    Offer::factory()->create(['chain' => Chain::Lidl]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<loc>'.url('/').'/</loc>', false)
        ->assertSee('chain=lidl', false)
        ->assertDontSee('chain=albert', false);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSeeText('# Slevohlídka')
        ->assertSeeText('Akce Lidl');
});

it('omezí registraci a obnovu hesla na pár pokusů za minutu z jedné IP', function (): void {
    config(['letaky.rate_limits.sensitive_writes_per_minute' => 2]);

    $this->post('/forgot-password', ['email' => 'nikdo@example.com'])->assertStatus(302);
    $this->post('/forgot-password', ['email' => 'nikdo@example.com'])->assertStatus(302);
    $this->post('/forgot-password', ['email' => 'nikdo@example.com'])->assertStatus(429);

    // Čtení stránek limit měnících požadavků nepočítá
    $this->get('/akce')->assertOk();
});

it('omezí zkoušení tokenu cronu', function (): void {
    config(['letaky.rate_limits.cron_per_minute' => 2, 'letaky.cron.token' => 'tajny']);

    $this->get('/cron/send-digests?token=a')->assertNotFound();
    $this->get('/cron/send-digests?token=b')->assertNotFound();
    $this->get('/cron/send-digests?token=c')->assertStatus(429);
});

it('manifest pro plochu telefonu má název, barvu webu a existující ikony (R55)', function (): void {
    $response = $this->get(route('manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('short_name', 'Slevohlídka')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('theme_color', config('letaky.theme_colors.light'));

    foreach ($response->json('icons') as $icon) {
        expect(public_path($icon['src']))->toBeFile();
    }

    $this->get(route('home'))->assertSee('<link rel="manifest" href="/manifest.webmanifest">', escape: false);
});
