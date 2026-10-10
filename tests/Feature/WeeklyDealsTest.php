<?php

/**
 * Nejlepší slevy týdne (R128): ISO týden po–ne, žebříček napříč obchody, slevy po obchodech,
 * archiv po týdnech a napojení na vyhledávače (sitemap, llms.txt, obsah bez JS, schema.org).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

use App\Domain\Offers\IsoWeek;
use App\Domain\Offers\LocalCalendar;
use App\Enums\Chain;
use App\Enums\OfferType;
use App\Models\Offer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    // Středa 41. týdne (5.–11. 10. 2026)
    $this->travelTo('2026-10-07 10:00:00');
});

/**
 * Akce se slevou a obrázkem v Kauflandu, platná celý 41. týden.
 *
 * @param  array<string, mixed>  $attributes
 */
function weeklyOffer(string $name, array $attributes = []): Offer
{
    return Offer::factory()->create([
        'name' => $name,
        'offer_type' => OfferType::Discount,
        'discount_percent' => 30,
        'image_url' => 'https://example.com/a.jpg',
        'valid_from' => '2026-10-05',
        'valid_to' => '2026-10-11',
        ...$attributes,
    ]);
}

/**
 * Názvy akcí z dat stránky.
 *
 * @param  iterable<array<string, mixed>>  $offers
 * @return list<string>
 */
function weeklyNames(iterable $offers): array
{
    return collect($offers)->pluck('name')->all();
}

it('týden je ISO týden pondělí–neděle i přes konec roku, v adrese s rokem ISO týdne', function (): void {
    $calendar = app(LocalCalendar::class);
    $newYear = IsoWeek::containing($calendar->date('2026-01-01'));

    expect($newYear->slug())->toBe('2026-01')
        ->and($newYear->monday()->toDateString())->toBe('2025-12-29')
        ->and($newYear->range())->toBe("29.\u{A0}12.\u{A0}2025 – 4.\u{A0}1.\u{A0}2026")
        ->and(IsoWeek::containing($calendar->date('2026-10-04'))->range())->toBe("28.\u{A0}9. – 4.\u{A0}10.\u{A0}2026")
        ->and(IsoWeek::containing($calendar->date('2026-10-07'))->range())->toBe("5.–11.\u{A0}10.\u{A0}2026")
        // 2026 má 53 týdnů, 2025 jen 52; jiný tvar adresy není týden
        ->and(IsoWeek::fromSlug('2026-53')?->next()->slug())->toBe('2027-01')
        ->and(IsoWeek::fromSlug('2025-53'))->toBeNull()
        ->and(IsoWeek::fromSlug('2026-00'))->toBeNull()
        ->and(IsoWeek::fromSlug('2026-1'))->toBeNull();
});

it('/tyden přesměruje na aktuální týden', function (): void {
    $this->get('/tyden')->assertRedirect('/tyden/2026-41');
});

it('aktuální týden: žebříček s obrázky napříč obchody a nejlepší slevy po obchodech, jen co se dá ještě koupit', function (): void {
    config(['letaky.weekly.top_offers' => 3, 'letaky.weekly.chain_offers' => 2]);
    weeklyOffer('Kaufland 60', ['discount_percent' => 60]);
    weeklyOffer('Kaufland 55', ['discount_percent' => 55]);
    weeklyOffer('Kaufland 50', ['discount_percent' => 50]);
    weeklyOffer('Lidl 40', ['chain' => Chain::Lidl, 'discount_percent' => 40]);
    weeklyOffer('Penny bez obrázku', ['chain' => Chain::Penny, 'discount_percent' => 70, 'image_url' => null]);
    // Začne až v pátek — v týdnu platí, patří tam
    weeklyOffer('Tesco od pátku', ['chain' => Chain::Tesco, 'valid_from' => '2026-10-09']);
    // Nepatří: skončila včera, stažená obchodem, příští týden, bez slevy
    weeklyOffer('Lidl skončená', ['chain' => Chain::Lidl, 'discount_percent' => 80, 'valid_to' => '2026-10-06']);
    weeklyOffer('Lidl stažená', ['chain' => Chain::Lidl, 'discount_percent' => 80, 'withdrawn_at' => now()]);
    weeklyOffer('Lidl příští týden', ['chain' => Chain::Lidl, 'discount_percent' => 80, 'valid_from' => '2026-10-12', 'valid_to' => '2026-10-18']);
    weeklyOffer('Lidl bez slevy', ['chain' => Chain::Lidl, 'offer_type' => OfferType::PromoPrice, 'discount_percent' => null]);

    $this->get('/tyden/2026-41')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Weekly')
        ->where('heading', "Nejlepší slevy týdne 5.–11.\u{A0}10.\u{A0}2026")
        ->where('week.slug', '2026-41')
        ->where('week.current', true)
        ->where('top', fn ($offers): bool => weeklyNames($offers) === ['Kaufland 60', 'Lidl 40', 'Tesco od pátku'])
        ->where('chainSections', fn ($sections): bool => collect($sections)->map(fn (array $section): array => [
            $section['chain'], $section['genitive'], weeklyNames($section['offers']),
        ])->all() === [
            ['kaufland', 'Kauflandu', ['Kaufland 60', 'Kaufland 55']],
            ['tesco', 'Tesca', ['Tesco od pátku']],
            ['lidl', 'Lidlu', ['Lidl 40']],
            ['penny', 'Penny', ['Penny bez obrázku']],
        ])
        ->where('chainSections.0.url', '/akce/kaufland')
        ->where('newerUrl', null)
        ->where('olderUrl', null)
        ->where('shareUrl', url('/tyden/2026-41')));
});

it('žebříček má z jednoho obchodu nejvýš nastavený počet akcí', function (): void {
    config(['letaky.weekly.top_offers' => 5, 'letaky.weekly.top_max_per_chain' => 2]);
    weeklyOffer('Kaufland 60', ['discount_percent' => 60]);
    weeklyOffer('Kaufland 55', ['discount_percent' => 55]);
    weeklyOffer('Kaufland 50', ['discount_percent' => 50]);
    weeklyOffer('Lidl 20', ['chain' => Chain::Lidl, 'discount_percent' => 20]);
    weeklyOffer('Lidl 10', ['chain' => Chain::Lidl, 'discount_percent' => 10]);

    $this->get('/tyden/2026-41')->assertInertia(fn (Assert $page) => $page
        ->where('top', fn ($offers): bool => weeklyNames($offers) === ['Kaufland 60', 'Kaufland 55', 'Lidl 20', 'Lidl 10']));
});

it('archiv: týden, který skončil, má všechny akce svých dnů, i skončené a stažené až během týdne', function (): void {
    $created = ['created_at' => '2026-09-29 08:00:00'];
    weeklyOffer('Platila do neděle', [...$created, 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04']);
    weeklyOffer('Stažená ve středu', [...$created, 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04', 'withdrawn_at' => '2026-09-30 12:00:00']);
    weeklyOffer('Stažená před týdnem', [...$created, 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04', 'withdrawn_at' => '2026-09-27 12:00:00']);
    // Leták st–út je ve dvou týdnech — v aktuálním už neplatí (skončil včera)
    weeklyOffer('Leták st–út', [...$created, 'valid_from' => '2026-09-30', 'valid_to' => '2026-10-06']);

    $this->get('/tyden/2026-40')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('week.current', false)
        ->where('top', fn ($offers): bool => collect(weeklyNames($offers))->sort()->values()->all() === ['Leták st–út', 'Platila do neděle', 'Stažená ve středu'])
        ->where('newerUrl', '/tyden/2026-41')
        ->where('olderUrl', null)
        ->where('archive', fn ($archive): bool => collect($archive)->map(fn (array $week): array => [$week['slug'], $week['active']])->all() === [
            ['2026-41', false],
            ['2026-40', true],
        ]));

    $this->get('/tyden/2026-41')->assertInertia(fn (Assert $page) => $page->where('top', []));
});

it('archiv začíná týdnem prvního stažení a vynechá týdny bez slev; budoucí a neplatný týden je 404', function (): void {
    weeklyOffer('Září', ['created_at' => '2026-09-15 08:00:00', 'valid_from' => '2026-09-15', 'valid_to' => '2026-09-16']);
    weeklyOffer('Říjen', ['created_at' => '2026-09-29 08:00:00', 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04']);

    $this->get('/tyden/2026-38')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('archive', fn ($archive): bool => collect($archive)->pluck('slug')->all() === ['2026-41', '2026-40', '2026-38'])
        // Sousední týden v archivu, ne kalendářní — 39. žádné slevy nemá
        ->where('newerUrl', '/tyden/2026-40'));

    $this->get('/tyden/2026-39')->assertNotFound();
    $this->get('/tyden/2026-37')->assertNotFound();
    $this->get('/tyden/2026-42')->assertNotFound();
    $this->get('/tyden/2026-60')->assertNotFound();
    $this->get('/tyden/41')->assertNotFound();
});

it('stránka týdne se indexuje: titulek, canonical, akce ve schema.org a obsah bez JavaScriptu s archivem', function (): void {
    weeklyOffer('Máslo Pilos', ['discount_percent' => 45]);
    weeklyOffer('Říjen', ['created_at' => '2026-09-29 08:00:00', 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04']);

    $html = (string) $this->get('/tyden/2026-41?utm_source=facebook')->assertOk()->getContent();

    expect($html)->toContain('<title inertia>Nejlepší slevy 41. týdne 2026 · Slevohlídka</title>')
        ->toContain('<meta name="robots" content="index, follow">')
        ->toContain('<link rel="canonical" href="'.url('/tyden/2026-41').'">')
        ->toContain('"@type":"CollectionPage"')
        ->toContain('"@type":"Offer","name":"Máslo Pilos"')
        // Obsah pro roboty bez JS: žebříček se slevou, sekce obchodu a archiv
        ->toContain('<h1>Nejlepší slevy týdne 5.–11.')
        ->toContain('<strong>Máslo Pilos</strong> · Kaufland')
        ->toContain('sleva 45 %')
        ->toContain('<h2>Kaufland — nejlepší slevy</h2>')
        ->toContain('<a href="/tyden/2026-40">40. týden 2026')
        ->toContain('<a href="/tyden/2026-40">'.__('app.ui.weekly.older').'</a>');

    $this->get('/tyden/2026-41')->assertInertia(fn (Assert $page) => $page->where('seoTitle', 'Nejlepší slevy 41. týdne 2026 · Slevohlídka'));
});

it('aktuální týden bez slev se neindexuje a není v sitemap.xml', function (): void {
    $html = (string) $this->get('/tyden/2026-41')->assertOk()->getContent();

    expect($html)->toContain('<meta name="robots" content="noindex, follow">');
    $this->get('/sitemap.xml')->assertDontSee('/tyden/', false);
});

it('sitemap.xml má týdny archivu: aktuální s poslední změnou akcí, skončený s koncem týdne', function (): void {
    weeklyOffer('Říjen', ['created_at' => '2026-09-29 08:00:00', 'valid_from' => '2026-09-28', 'valid_to' => '2026-10-04']);
    weeklyOffer('Tento týden', ['created_at' => '2026-10-06 05:30:00']);
    // Leták na příští týden ani akce bez slevy aktuální týden nezmění
    weeklyOffer('Příští týden', ['created_at' => '2026-10-07 06:00:00', 'valid_from' => '2026-10-12', 'valid_to' => '2026-10-18']);
    weeklyOffer('Bez slevy', ['created_at' => '2026-10-07 06:00:00', 'offer_type' => OfferType::PromoPrice, 'discount_percent' => null]);

    $this->get('/sitemap.xml')
        ->assertSee('<loc>'.url('/tyden/2026-41')."</loc>\n        <lastmod>2026-10-06T05:30:00+00:00</lastmod>", false)
        // Konec 40. týdne = pondělí 5. 10. 00:00 v Praze
        ->assertSee('<loc>'.url('/tyden/2026-40')."</loc>\n        <lastmod>2026-10-04T22:00:00+00:00</lastmod>", false);
});

it('odkazy na týden: llms.txt, navigace nepřihlášeného, patička a úvodní stránka', function (): void {
    weeklyOffer('Máslo Pilos');

    $this->get('/llms.txt')->assertSee('[Nejlepší slevy týdne]('.url('/tyden/2026-41').')', false);
    $this->get('/akce')
        ->assertSee('<a href="'.url('/tyden/2026-41').'">Nejlepší slevy týdne</a>', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('navigation.1.url', '/tyden')
            ->where('navigation.1.label', 'nav.weekly')
            ->where('siteFooter.weeklyUrl', '/tyden'));
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('urls.weekly', '/tyden'));

    // Přihlášený má ve spodní liště pět záložek — týden jen v patičce
    $this->actingAs(User::factory()->create())->get('/akce')->assertInertia(fn (Assert $page) => $page
        ->where('navigation', fn ($items): bool => ! collect($items)->contains('url', '/tyden')));
});

it('navigace nepřihlášeného označí týden jako aktivní i na stránce konkrétního týdne', function (): void {
    $this->get('/tyden/2026-41')->assertInertia(fn (Assert $page) => $page->where('navigation.1.active', true));
});
