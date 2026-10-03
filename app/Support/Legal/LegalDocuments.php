<?php

/**
 * Právní texty (R51): podmínky užití a zásady zpracování osobních údajů jako Markdown
 * v resources/legal. Dlouhý strukturovaný text se v Markdownu dá číst, porovnávat mezi
 * verzemi i dát k posouzení právníkovi — proto výjimka z pravidla „texty v lang/cs/app.php“.
 * Údaje provozovatele se doplní z config/letaky.php ({operator}, {company_id}, {address},
 * {email}, {site_url}). Text je náš, ne od obchodu; syrové HTML v něm se přesto zahodí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Support\Legal;

use App\Support\Operator;
use App\Support\Seo\SeoMeta;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

final class LegalDocuments
{
    /** Podmínky užití — resources/legal/terms.md. */
    public const TERMS = 'terms';

    /** Zásady zpracování osobních údajů — resources/legal/privacy.md. */
    public const PRIVACY = 'privacy';

    /** Kapitoly dokumentu = nadpisy druhé úrovně (## v Markdownu). */
    private const SECTION_HEADING = '~<h2>(.*?)</h2>~s';

    /**
     * Dokument jako HTML s doplněnými údaji provozovatele a obsah z kapitol. Nadpis kapitoly
     * dostane id ze svého textu („4-kdo-k-udajum-ma-pristup“) — cíl odkazu z obsahu stránky
     * i odkazu zvenku (/ochrana-udaju#5-cookies-a-uloziste-v-prohlizeci).
     *
     * @return array{html: string, sections: list<array{id: string, title: string}>}
     */
    public function render(string $document): array
    {
        $markdown = File::get(resource_path(config()->string('letaky.legal.directory').'/'.$document.'.md'));
        $html = Str::markdown(
            strtr($markdown, $this->replacements()),
            ['html_input' => 'strip', 'allow_unsafe_links' => false],
            [new TableExtension],
        );

        $sections = [];
        $html = (string) preg_replace_callback(self::SECTION_HEADING, function (array $match) use (&$sections): string {
            $title = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5);
            $id = Str::slug($title);
            $sections[] = ['id' => $id, 'title' => $title];

            return '<h2 id="'.e($id).'">'.$match[1].'</h2>';
        }, $html);

        return ['html' => $html, 'sections' => $sections];
    }

    /**
     * Zástupné značky => hodnoty; chybějící údaj provozovatele je na stránce vidět.
     *
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $missing = __('app.legal.missing');
        $operator = config()->array('letaky.operator');

        return [
            '{operator}' => (string) ($operator['name'] ?? $missing),
            '{company_id}' => (string) ($operator['company_id'] ?? $missing),
            '{address}' => app(Operator::class)->address() ?? $missing,
            '{email}' => (string) ($operator['email'] ?? $missing),
            '{site_url}' => SeoMeta::homeUrl(),
        ];
    }
}
