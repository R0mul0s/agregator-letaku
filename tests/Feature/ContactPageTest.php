<?php

/**
 * Stránka Kontakt a kontakt v patičce (R72).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('ukáže provozovatele s IČO, sídlem, zápisem v rejstříku, e-mailem a telefonem', function (): void {
    $this->get(route('contact'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Contact')
            ->where('operator.name', 'Roman Hlaváček')
            ->where('operator.brand', 'RHsoft.cz')
            ->where('operator.companyId', '88688143')
            ->where('operator.address', 'Rodov 133, 503 03 Smiřice')
            ->where('operator.tradeOffice', 'Magistrát města Hradec Králové')
            ->where('operator.email', 'info@slevohlidka.cz')
            ->where('operator.phone', '+420 736 449 607')
            // Odkaz tel: bez mezer
            ->where('operator.phoneHref', '+420736449607')
            ->where('urls.privacy', '/ochrana-udaju'));
});

it('je veřejná a indexovaná, v sitemap i llms.txt', function (): void {
    $html = $this->get(route('contact'))->getContent();

    expect($html)->toContain('<meta name="robots" content="index, follow">')
        ->and($html)->toContain('<title inertia>Kontakt · Slevohlídka</title>');
    $this->get('/sitemap.xml')->assertSee('<loc>'.route('contact').'</loc>', false);
    $this->get('/llms.txt')->assertSee(route('contact'), false);
});

it('právní texty doplní živnostenský úřad provozovatele a píší za provozovatele „my“', function (): void {
    $this->get(route('legal.terms'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('html', fn (string $html): bool => str_contains($html, 'živnostenském rejstříku (Magistrát města Hradec Králové)')
                && ! str_contains($html, '[doplnit]')));
});

it('každé téma rozcestníku má název, předmět i osnovu e-mailu (R91)', function (): void {
    foreach (trans('app.ui.contact.topics') as $topic => $texts) {
        expect($texts)->toHaveKeys(['title', 'text', 'subject', 'template'], "téma {$topic}")
            ->and($texts['template'])->toContain("\n");
    }
});
