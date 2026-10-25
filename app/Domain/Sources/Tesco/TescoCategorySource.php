<?php

/**
 * Strom kategorií e-shopu Tesco jako zdroj kategorií katalogu (R28, ZDROJE_DAT.md, Tesco).
 *
 * Dotaz `taxonomy` na stejné GraphQL API jako akce e-shopu vrací 4 úrovně: oddělení › sekce ›
 * regál › police. Marketingová oddělení z konfigurace se vynechají i s podstromem, stejně tak sekce
 * oddělení, které v letácích nemají zboží (knihy, mobilní tarify, R174).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Tesco;

use App\Domain\Catalog\Data\CategoryData;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Sources\Exceptions\SourceNotConfigured;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;

final class TescoCategorySource
{
    /** Strom do hloubky 4 úrovní — hlubší Tesco nemá (2. 10. 2026). */
    private const TAXONOMY_QUERY = 'query Taxonomy { taxonomy { id name children { id name children { id name children { id name } } } } }';

    public function __construct(private readonly SourceHttp $http) {}

    /**
     * Kategorie stromu v pořadí rodič před potomky, bez vyloučených oddělení.
     *
     * @return list<CategoryData>
     *
     * @throws SourceNotConfigured
     * @throws SourceResponseChanged
     */
    public function fetch(): array
    {
        $apiKey = config('letaky.sources.tesco.eshop_api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw SourceNotConfigured::missing(Chain::Tesco, 'TESCO_API_KEY');
        }

        $response = $this->http->request()
            ->withHeaders(['x-apikey' => $apiKey, 'region' => 'CZ', 'language' => 'cs-CZ'])
            ->post(config()->string('letaky.sources.tesco.eshop_api_url'), [['query' => self::TAXONOMY_QUERY]])
            ->json();

        return $this->categories(is_array($response) ? $response : []);
    }

    /**
     * Rozbalí strom z odpovědi do seznamu kategorií.
     *
     * @param  array<mixed>  $response
     * @return list<CategoryData>
     *
     * @throws SourceResponseChanged
     */
    public function categories(array $response): array
    {
        $roots = $response[0]['data']['taxonomy'] ?? null;
        if (! is_array($roots) || $roots === []) {
            throw SourceResponseChanged::because(Chain::Tesco, 'strom kategorií je prázdný');
        }

        $roots = $this->withoutExcluded($roots, config()->array('letaky.categories.excluded_roots'));
        $excludedSections = config()->array('letaky.categories.excluded_sections');
        foreach ($roots as $key => $root) {
            if (is_array($root) && is_array($root['children'] ?? null)) {
                $roots[$key]['children'] = array_values($this->withoutExcluded($root['children'], $excludedSections));
            }
        }

        $categories = [];
        $this->collect(array_values($roots), null, 0, $categories);

        return $categories;
    }

    /**
     * Uzly jedné úrovně bez těch, jejichž název je mezi vyloučenými.
     *
     * @param  array<mixed>  $nodes
     * @param  array<mixed>  $excludedNames
     * @return array<mixed>
     */
    private function withoutExcluded(array $nodes, array $excludedNames): array
    {
        $excluded = array_map(strval(...), $excludedNames);

        return array_filter($nodes, fn (mixed $node): bool => ! is_array($node) || ! in_array($node['name'] ?? null, $excluded, true));
    }

    /**
     * Přidá uzly jedné úrovně a jejich podstromy (rodič vždy před potomky).
     *
     * @param  array<mixed>  $nodes
     * @param  list<CategoryData>  $categories
     */
    private function collect(array $nodes, ?string $parentSourceId, int $depth, array &$categories): void
    {
        $position = 0;
        foreach ($nodes as $node) {
            $id = is_array($node) && is_string($node['id'] ?? null) ? $node['id'] : null;
            $name = is_array($node) ? Text::clean(is_string($node['name'] ?? null) ? $node['name'] : null) : null;
            if ($id === null || $name === null) {
                continue;
            }

            $categories[] = new CategoryData($id, $name, $depth, $position++, $parentSourceId);
            if (is_array($node['children'] ?? null)) {
                $this->collect($node['children'], $id, $depth + 1, $categories);
            }
        }
    }
}
