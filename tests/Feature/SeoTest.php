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
use Inertia\Testing\AssertableInertia as Assert;

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
        ->and(metaContent($html, 'og:title'))->toBe('Aktuální akce Kauflandu · Slevohlídka');

    // Hledání (noindex) odkazuje samo na sebe, ne na výpis bez hledání (R68)
    $search = $this->get('/akce?q=vejce&chain=lidl')->getContent();
    expect(metaContent($search, 'robots'))->toBe('noindex, follow')
        ->and($search)->toContain('<link rel="canonical" href="'.route('offers').'?q=vejce&amp;chain=lidl">')
        ->and($search)->not->toContain('application/ld+json');
});

it('výpis obchodu jen se zmínkami v letácích (Albert) se neindexuje (R68)', function (): void {
    $html = $this->get('/akce?chain=albert')->getContent();

    expect(metaContent($html, 'robots'))->toBe('noindex, follow');
});

it('titulek ze serveru dostane i Vue, aby ho <Head> nepřepsal (R68)', function (): void {
    $this->get('/akce?chain=billa')->assertInertia(fn (Assert $page) => $page->where('seoTitle', 'Aktuální akce Billy · Slevohlídka'));
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('seoTitle', __('app.seo.pages.home.title')));
});

it('titulky a popisy veřejných stránek mají délku vhodnou pro výsledky hledání (R68)', function (string $page): void {
    expect(mb_strlen(__("app.seo.pages.{$page}.title", ['chain' => 'Kauflandu'])))->toBeLessThanOrEqual(65)
        ->and(mb_strlen(__("app.seo.pages.{$page}.description", ['chain' => 'Kauflandu'])))->toBeLessThanOrEqual(160);
})->with(['home', 'offers', 'offers_chain', 'terms', 'privacy']);

it('přihlášení se neindexuje, stránky za přihlášením ani nesledují', function (): void {
    expect(metaContent($this->get('/login')->getContent(), 'robots'))->toBe('noindex, follow');

    $html = $this->actingAs(User::factory()->create())->get('/hlidam')->getContent();
    expect(metaContent($html, 'robots'))->toBe('noindex, nofollow');
});

it('za proxy bere IP klienta z X-Forwarded-For, adresy ale vždy z APP_URL (R67)', function (): void {
    $html = $this->withHeaders([
        'X-Forwarded-For' => '203.0.113.7',
        // Proxy hostingu propouští X-Forwarded-Prefix od klienta — nesmí se dostat do adres
        'X-Forwarded-Prefix' => '/zly',
    ])->get('/akce')->getContent();

    expect(request()->ip())->toBe('203.0.113.7')
        ->and($html)->toContain('<link rel="canonical" href="'.rtrim(config()->string('app.url'), '/').'/akce">');
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
        ->assertSeeText('Akce Lidlu');
});

it('sitemap.xml: právní stránky mají datum účinnosti, ne čas stažení akcí (R68)', function (): void {
    config(['letaky.legal.effective_from' => '2026-10-04']);

    $this->get('/sitemap.xml')
        ->assertSee('<loc>'.route('legal.terms').'</loc>'."\n".'        <lastmod>2026-10-04</lastmod>', false)
        ->assertDontSee('changefreq', false);
});

it('security.txt má kontakt, platnost do půl roku a canonical (R68)', function (): void {
    $this->get('/.well-known/security.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSeeText('Contact: mailto:'.config('letaky.operator.email'))
        ->assertSeeText('Expires: 2027-03-31T00:00:00Z')
        ->assertSeeText('Canonical: '.route('security-txt'));
});

it('omezí registraci a obnovu hesla na pár pokusů za minutu z jedné IP', function (): void {
    config(['letaky.rate_limits.sensitive_writes_per_minute' => 2]);

    $this->post('/forgot-password', ['email' => 'nikdo@example.com'])->assertStatus(302);
    $this->post('/forgot-password', ['email' => 'nikdo@example.com'])->assertStatus(302);
    $this->post('/forgot-password', ['email' => 'nikdo@example.com'])->assertStatus(429);

    // Čtení stránek limit měnících požadavků nepočítá
    $this->get('/akce')->assertOk();
});

it('omezí opakované posílání ověřovacího e-mailu i za hodinu (R67)', function (): void {
    config(['letaky.rate_limits.emails_per_hour' => 2]);
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)->post(route('verification.send'))->assertStatus(302);
    $this->travel(2)->minutes();
    $this->actingAs($user)->post(route('verification.send'))->assertStatus(302);
    // Minutový limit už neplatí, hodinový ano
    $this->travel(2)->minutes();
    $this->actingAs($user)->post(route('verification.send'))->assertStatus(429);
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
