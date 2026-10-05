<?php

/**
 * Stránka Kontakt (R72) — kdo Slevohlídku provozuje (jméno, IČO, sídlo, zápis v živnostenském
 * rejstříku), e-mail a telefon, rozcestník „S čím se ozvat“ a časté otázky. Údaje provozovatele
 * jsou v config/letaky.php (operator), texty v lang (ui.contact).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Operator;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Zobrazí kontaktní stránku.
     */
    public function __invoke(Operator $operator): Response
    {
        return Inertia::render('Contact', [
            'operator' => [
                'name' => config('letaky.operator.name'),
                'brand' => config('letaky.operator.brand'),
                'companyId' => config('letaky.operator.company_id'),
                'address' => $operator->address(),
                'tradeOffice' => config('letaky.operator.trade_office'),
                'email' => config('letaky.operator.email'),
                'phone' => config('letaky.operator.phone'),
                'phoneHref' => $operator->phoneHref(),
            ],
            'urls' => [
                'terms' => route('legal.terms', absolute: false),
                'privacy' => route('legal.privacy', absolute: false),
            ],
        ]);
    }
}
