<?php

/**
 * Strom kategorií e-shopu Tesco jako zdroj kategorií katalogu (R28, ZDROJE_DAT.md, Tesco).
 *
 * Dotaz `taxonomy` na stejné GraphQL API jako akce e-shopu vrací 4 úrovně: oddělení › sekce ›
 * regál › police. Marketingová a nepotravinová oddělení z konfigurace se vynechají i s podstromem.
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

        $excluded = array_map(strval(...), config()->array('letaky.categories.excluded_roots'));
        $roots = array_filter($roots, fn (mixed $root): bool => ! is_array($root) || ! in_array($root['name'] ?? null, $excluded, true));

        $categories = [];
        $this->collect(array_values($roots), null, 0, $categories);

        return $categories;
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
