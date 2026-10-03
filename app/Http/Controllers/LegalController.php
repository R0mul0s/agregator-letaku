<?php

/**
 * Veřejné právní stránky (R51): podmínky užití a zásady zpracování osobních údajů.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Legal\LegalDocuments;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function __construct(private readonly LegalDocuments $documents) {}

    /**
     * Podmínky užití.
     */
    public function terms(): Response
    {
        return $this->render(LegalDocuments::TERMS);
    }

    /**
     * Zásady zpracování osobních údajů.
     */
    public function privacy(): Response
    {
        return $this->render(LegalDocuments::PRIVACY);
    }

    /**
     * Stránka s textem dokumentu, obsahem z kapitol a datem účinnosti.
     */
    private function render(string $document): Response
    {
        $rendered = $this->documents->render($document);

        return Inertia::render('Legal', [
            'title' => __('app.legal.'.$document),
            'html' => $rendered['html'],
            'sections' => $rendered['sections'],
            'effectiveFrom' => config('letaky.legal.effective_from'),
        ]);
    }
}
