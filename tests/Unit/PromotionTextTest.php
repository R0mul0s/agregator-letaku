<?php

/**
 * Text akce na více kusů pro zobrazení (R101) — verzálky od obchodu jako věta.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

use App\Domain\Offers\PromotionText;

it('verzálky převede na větu s diakritikou známých slov a mezerami kolem „+“', function (?string $text, ?string $display): void {
    expect((new PromotionText)->forDisplay($text))->toBe($display);
})->with([
    'Tesco menu' => ['PECIVO+NAPOJ', 'Pečivo + nápoj'],
    'Tesco pizza' => ['MENU PIZZA+COLA', 'Menu pizza + cola'],
    'Tesco bageta' => ['MENU BAGETY', 'Menu bagety'],
    'Billa s malými písmeny' => ['od 3 ks: 19,90 Kč', 'od 3 ks: 19,90 Kč'],
    'Tesco věta' => ['3 za cenu 2 Clubcard cena - Nejlevnější produkt zdarma', '3 za cenu 2 Clubcard cena - Nejlevnější produkt zdarma'],
    'Lidl' => ['4+2 zdarma', '4 + 2 zdarma'],
    'bez písmen' => ['2+1', '2 + 1'],
    'prázdný' => ['  ', null],
    'žádný' => [null, null],
]);
