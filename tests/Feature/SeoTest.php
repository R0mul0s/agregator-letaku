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
use App\Enums\MatchStatus;
use App\Enums\PackageUnit;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Support\Seo\PublicPages;
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

it('hlavička má ověření pro Seznam Webmaster, prázdný kód značku vynechá', function (): void {
    config(['letaky.site_verification.seznam' => 'kod123']);
    expect(metaContent($this->get('/')->getContent(), 'seznam-wmt'))->toBe('kod123');

    config(['letaky.site_verification.seznam' => '']);
    expect($this->get('/')->getContent())->not->toContain('seznam-wmt');
});

it('Všechny akce: obchod a stránka v canonical, rozsah a hledání ne; hledání se neindexuje', function (): void {
    $html = $this->get('/akce/kaufland?od=1&strana=2')->getContent();
    expect($html)->toContain('<link rel="canonical" href="'.url('/akce/kaufland').'?strana=2">')
        ->and(metaContent($html, 'robots'))->toBe('index, follow')
        ->and(metaContent($html, 'og:title'))->toBe('Akce z letáku Kauflandu tento týden · Slevohlídka');

    // Hledání (noindex) odkazuje samo na sebe, ne na výpis bez hledání (R68)
    $search = $this->get('/akce/lidl?q=vejce')->getContent();
    expect(metaContent($search, 'robots'))->toBe('noindex, follow')
        ->and($search)->toContain('<link rel="canonical" href="'.url('/akce/lidl').'?q=vejce">')
        ->and($search)->not->toContain('application/ld+json');
});

it('výpis jen akcí, které ještě nezačaly, se neindexuje (R76)', function (): void {
    expect(metaContent($this->get('/akce?brzy=1')->getContent(), 'robots'))->toBe('noindex, follow');
});

it('víc obchodů najednou a bez e-shopu se neindexuje (R82)', function (): void {
    expect(metaContent($this->get('/akce?chain=kaufland,lidl')->getContent(), 'robots'))->toBe('noindex, follow')
        ->and(metaContent($this->get('/akce?chain=vse')->getContent(), 'robots'))->toBe('noindex, follow')
        ->and(metaContent($this->get('/akce?bez-eshopu=1')->getContent(), 'robots'))->toBe('noindex, follow');
});

it('výpis Albertu se indexuje jako ostatní obchody — má akce s cenou z PDF letáku (R86)', function (): void {
    $html = $this->get('/akce/albert')->getContent();

    expect(metaContent($html, 'robots'))->toBe('index, follow');
});

it('titulek ze serveru dostane i Vue, aby ho <Head> nepřepsal (R68)', function (): void {
    $this->get('/akce/billa')->assertInertia(fn (Assert $page) => $page->where('seoTitle', 'Akce z letáku Billy tento týden · Slevohlídka'));
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('seoTitle', __('app.seo.pages.home.title')));
});

it('titulky a popisy veřejných stránek mají délku vhodnou pro výsledky hledání (R68)', function (string $page): void {
    // Nejdelší hodnoty: obchod a týden přes konec roku (R128)
    $replace = ['chain' => 'Kauflandu', 'number' => '53', 'year' => '2026', 'range' => "29.\u{A0}12.\u{A0}2025 – 4.\u{A0}1.\u{A0}2026"];
    expect(mb_strlen(__("app.seo.pages.{$page}.title", $replace)))->toBeLessThanOrEqual(65)
        ->and(mb_strlen(__("app.seo.pages.{$page}.description", $replace)))->toBeLessThanOrEqual(160);
})->with(['home', 'offers', 'offers_chain', 'weekly', 'terms', 'privacy']);

it('přihlášení se neindexuje, stránky za přihlášením ani nesledují', function (): void {
    expect(metaContent($this->get('/prihlaseni')->getContent(), 'robots'))->toBe('noindex, follow');

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
        ->assertDontSeeText('Disallow: /prihlaseni')
        ->assertSeeText('Sitemap: '.route('sitemap'));
});

it('sitemap.xml a llms.txt obsahují jen obchody s aktuálními akcemi', function (): void {
    Offer::factory()->create(['chain' => Chain::Lidl]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<loc>'.url('/').'/</loc>', false)
        ->assertSee('<loc>'.url('/akce/lidl').'</loc>', false)
        ->assertDontSee('/akce/albert', false);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
        ->assertSeeText('# Slevohlídka')
        ->assertSeeText('Akce Lidlu');
});

it('sitemap.xml: lastmod stránky akcí je poslední přibytí nebo stažení akce na ní, ne čas stažení (R122)', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    $validity = ['valid_from' => '2026-10-01', 'valid_to' => '2026-10-31'];

    $this->travelTo('2026-10-05 08:00:00');
    Offer::factory()->create(['chain' => Chain::Lidl, ...$validity])
        ->productAssignments()->create(['product_id' => $beer->id, 'status' => MatchStatus::Match, 'is_manual' => false]);
    $withdrawn = Offer::factory()->create(['chain' => Chain::Lidl, ...$validity]);

    $this->travelTo('2026-10-06 08:00:00');
    Offer::factory()->create(['chain' => Chain::Albert, ...$validity]);

    // Obchod akci stáhl — stránka Lidlu se změnila, stránka piva ne
    $this->travelTo('2026-10-07 08:00:00');
    $withdrawn->forceFill(['withdrawn_at' => now()])->save();

    // Další stažení beze změny datum neposune
    $this->travelTo('2026-10-08 12:00:00');
    $lastmod = fn (string $path, string $at): string => '<loc>'.url($path).'</loc>'."\n".'        <lastmod>'.$at.'</lastmod>';

    $this->get('/sitemap.xml')
        ->assertSee($lastmod('/akce', '2026-10-07T08:00:00+00:00'), false)
        ->assertSee($lastmod('/akce/lidl', '2026-10-07T08:00:00+00:00'), false)
        ->assertSee($lastmod('/akce/albert', '2026-10-06T08:00:00+00:00'), false)
        ->assertSee($lastmod('/akce/pivo', '2026-10-05T08:00:00+00:00'), false);
});

it('sitemap.xml: kontakt má datum účinnosti právních textů, ne čas stažení akcí (R68)', function (): void {
    config(['letaky.legal.effective_from' => '2026-10-04']);

    $this->get('/sitemap.xml')
        ->assertSee('<loc>'.route('contact').'</loc>'."\n".'        <lastmod>2026-10-04</lastmod>', false)
        ->assertDontSee('changefreq', false);
});

it('podmínky a zásady jsou veřejné, ale noindex a mimo sitemap; kontakt indexovaný (R121)', function (): void {
    expect(PublicPages::indexedRoutes())->toBe(['contact']);

    $this->get('/sitemap.xml')
        ->assertDontSee('<loc>'.route('legal.terms').'</loc>', false)
        ->assertDontSee('<loc>'.route('legal.privacy').'</loc>', false)
        ->assertSee('<loc>'.route('contact').'</loc>', false);
});

it('každá stránka z registru veřejných stránek má robots podle registru, je v llms.txt i v obsahu bez JS (R113, R121)', function (string $route, string $kind): void {
    expect(__("app.seo.pages.$kind.title"))->not->toBe("app.seo.pages.$kind.title")
        ->and(__("app.ui.footer.$kind"))->not->toBe("app.ui.footer.$kind")
        ->and(__("app.llms.$kind"))->not->toBe("app.llms.$kind");

    $html = $this->get(route($route))->assertOk()->getContent();
    $indexed = PublicPages::isIndexed($kind);
    expect(metaContent($html, 'robots'))->toBe($indexed ? 'index, follow' : 'noindex, follow');

    $sitemap = $this->get('/sitemap.xml');
    $indexed ? $sitemap->assertSee('<loc>'.route($route).'</loc>', false) : $sitemap->assertDontSee('<loc>'.route($route).'</loc>', false);
    $this->get('/llms.txt')->assertSee('('.route($route).')', false);
    $this->get('/akce')->assertSee('<a href="'.route($route).'">'.__("app.ui.footer.$kind").'</a>', false);
})->with(fn (): array => array_map(null, array_keys(PublicPages::PAGES), array_values(PublicPages::PAGES)));

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

    $this->post('/zapomenute-heslo', ['email' => 'nikdo@example.com'])->assertStatus(302);
    $this->post('/zapomenute-heslo', ['email' => 'nikdo@example.com'])->assertStatus(302);
    $this->post('/zapomenute-heslo', ['email' => 'nikdo@example.com'])->assertStatus(429);

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

it('stránka produktu a sitemap: titulek s produktem, čistá adresa v sitemap jen s akcemi (R94)', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    Product::factory()->create(['name' => 'Máslo']);
    Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Braník 0,5 l'])
        ->productAssignments()->create(['product_id' => $beer->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    $html = $this->get('/akce/pivo')->getContent();
    expect(metaContent($html, 'og:title'))->toBe('Pivo v akci — kde je nejlevněji · Slevohlídka')
        ->and($html)->toContain('<link rel="canonical" href="'.url('/akce/pivo').'">');

    $this->get('/sitemap.xml')
        ->assertSee('<loc>'.url('/akce/pivo').'</loc>', false)
        ->assertDontSee('/akce/maslo', false);
});

it('přihlášení a registrace mají vlastní titulek a nadpis i bez JavaScriptu, zůstávají noindex (R123)', function (string $route, string $kind, string $other): void {
    $html = $this->get(route($route))->assertOk()->getContent();

    expect($html)->toContain('<title inertia>'.__("app.seo.pages.$kind.title").'</title>')
        ->and($html)->toContain('<h1>'.__("app.seo.pages.$kind.heading").'</h1>')
        ->and($html)->toContain('href="'.route($other).'"')
        ->and(metaContent($html, 'robots'))->toBe('noindex, follow');
})->with([
    'přihlášení' => ['login', 'login', 'register'],
    'registrace' => ['register', 'register', 'login'],
]);

it('stránka přednačte písma a loga obchodů mají poměr stran — text ani řada log po načtení neposkočí (R123)', function (): void {
    $this->get(route('offers'))
        ->assertSee('as="font" type="font/woff2" crossorigin', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('chainInfo.tesco.logoSize', ['width' => 346, 'height' => 100])
            ->where('chainInfo.lidl.logoSize', ['width' => 100, 'height' => 100]));
});

it('obsah pro roboty bez JavaScriptu: nadpis, akce s cenou a odkazy na obchody a produkty (R94)', function (): void {
    $beer = Product::factory()->create(['name' => 'Pivo']);
    Offer::factory()->create(['chain' => Chain::Lidl, 'name' => 'Braník 0,5 l', 'price' => 1990, 'quantity' => 500, 'unit' => PackageUnit::Milliliter])
        ->productAssignments()->create(['product_id' => $beer->id, 'status' => MatchStatus::Match, 'is_manual' => false]);

    $html = $this->get('/akce/lidl')->getContent();
    expect($html)->toContain('data-seo-content')
        ->and($html)->toContain('<h1>Akce z letáku Lidlu</h1>')
        ->and($html)->toContain('<strong>Braník 0,5 l</strong>')
        ->and($html)->toContain("19,90\u{00A0}Kč")
        ->and($html)->toContain('href="/akce/pivo"');

    expect($this->get('/kontakt')->getContent())->toContain(e(__('app.ui.contact.faq.free.question')));

    // Stránky za přihlášením obsah pro roboty nemají
    $this->actingAs(User::factory()->create());
    expect($this->get('/hlidam')->getContent())->not->toContain('data-seo-content');
});

it('úvodní stránka bez JavaScriptu vysvětlí účel aplikace a odkáže na zásady a podmínky (R96)', function (): void {
    $html = (string) $this->get('/')->getContent();

    expect($html)->toContain(e(__('app.ui.landing.features_title')))
        ->and($html)->toContain(e(__('app.ui.landing.features.watch.text')))
        ->and($html)->toContain('href="'.route('legal.privacy').'"')
        ->and($html)->toContain('href="'.route('legal.terms').'"');
});
