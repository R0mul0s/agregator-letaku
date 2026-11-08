<?php

/**
 * Zdroj akční nabídky e-shopu Košík.cz — JSON API webu po odděleních akcí (R179, ZDROJE_DAT.md, Košík).
 *
 * Kategorie „akce“ celá kurzorem stáhnout nejde (kurzor skončí po ~560 z ~1 800 položek), proto se
 * prochází po odděleních (`akce/{oddělení}`, první stránka GET, další POST s kurzorem, nejvýš 30 položek
 * na stránku). Úvodní stránka „akce“ dá jen celkový počet: oddělení, která stahujeme nebo vědomě
 * vynecháváme (lékárna Dr. Max — jiný prodejce), musí pokrýt skoro všechny akce, jinak Košík přidal
 * oddělení a stažení skončí chybou (jen část nabídky by zbytek označila jako stažený, R16).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kosik;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use Illuminate\Http\Client\RequestException;

final class KosikOfferSource implements OfferSource
{
    /** Oddělovač kategorie akcí a oddělení v parametru `slug` („akce/mlecne-a-chlazene“). */
    private const SLUG_SEPARATOR = '/';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly KosikParser $parser,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Košík.
     */
    public function chain(): Chain
    {
        return Chain::Kosik;
    }

    /**
     * Jedna dávka „akce e-shopu“ se všemi akcemi sledovaných oddělení.
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    public function fetch(): array
    {
        $root = config()->string('letaky.sources.kosik.offers_slug');
        $total = $this->parser->firstPage($this->firstPageResponse($root), $root)['total'];
        $today = $this->calendar->today();

        $covered = 0;
        $items = 0;
        $offers = [];
        $skipped = 0;
        foreach (config()->array('letaky.sources.kosik.excluded_departments') as $department) {
            $slug = $root.self::SLUG_SEPARATOR.$department;
            $covered += $this->parser->firstPage($this->firstPageResponse($slug), $slug)['total'];
        }
        foreach (config()->array('letaky.sources.kosik.departments') as $department) {
            $slug = $root.self::SLUG_SEPARATOR.$department;
            [$departmentTotal, $departmentItems] = $this->departmentItems($slug);
            $covered += $departmentTotal;

            foreach ($departmentItems as $item) {
                if ($this->parser->vendorId($item) !== config()->integer('letaky.sources.kosik.vendor_id') || $this->parser->hasEnded($item, $today)) {
                    continue;
                }
                $items++;
                $offer = $this->parser->offer($item, $today);
                if ($offer === null) {
                    $skipped++;

                    continue;
                }
                // Stejný produkt ve dvou odděleních se uloží jednou
                $offers[$offer->externalId] = $offer;
            }
        }

        $this->assertComplete($total, $covered, $items, $skipped);

        return [new SourceBatch($this->eshopLeaflet(), array_values($offers))];
    }

    /**
     * Celkový počet a všechny položky oddělení: první stránka, pak další s kurzorem, dokud nejsou
     * všechny (nebo dokud API vrací kurzor a položky).
     *
     * @return array{int, list<array<string, mixed>>}
     *
     * @throws SourceResponseChanged
     * @throws RequestException
     */
    private function departmentItems(string $slug): array
    {
        $page = $this->parser->firstPage($this->firstPageResponse($slug), $slug);
        $items = $page['items'];
        $cursor = $page['cursor'];
        $maxPages = config()->integer('letaky.sources.kosik.max_pages_per_department');

        for ($loaded = 1; $cursor !== null && count($items) < $page['total']; $loaded++) {
            if ($loaded >= $maxPages) {
                throw SourceResponseChanged::because(Chain::Kosik, "oddělení {$slug} má víc než {$maxPages} stránek");
            }

            $response = $this->http->request($this->delay())
                ->post($this->url('more_api_path'), ['cursor' => $cursor, 'limit' => config()->integer('letaky.sources.kosik.page_size')])
                ->json();
            $next = $this->parser->nextPage(is_array($response) ? $response : [], $slug);
            if ($next['items'] === []) {
                break;
            }
            array_push($items, ...$next['items']);
            $cursor = $next['cursor'];
        }

        return [$page['total'], $items];
    }

    /**
     * Pojistky proti neúplnému stažení (R16, R54): oddělení musí pokrýt skoro všechny akce kategorie
     * a skoro každá položka se musí převést — jinak se změnilo API (nové oddělení, jiný popisek platnosti).
     *
     * @throws SourceResponseChanged
     */
    private function assertComplete(int $total, int $covered, int $items, int $skipped): void
    {
        $maxMissing = config()->float('letaky.sources.kosik.max_missing_share');

        if ($covered < $total * (1 - $maxMissing)) {
            throw SourceResponseChanged::because(Chain::Kosik, "oddělení pokrývají jen {$covered} z {$total} akcí — přibylo oddělení?");
        }
        if ($skipped > $items * $maxMissing) {
            throw SourceResponseChanged::because(Chain::Kosik, "{$skipped} z {$items} položek nejde převést (cena, název nebo „Akce platí do“)");
        }
    }

    /**
     * První stránka kategorie nebo oddělení akcí.
     *
     * @return array<mixed>
     *
     * @throws RequestException
     */
    private function firstPageResponse(string $slug): array
    {
        $response = $this->http->request($this->delay())
            ->get($this->url('page_api_path'), ['slug' => $slug, 'limit' => config()->integer('letaky.sources.kosik.page_size')])
            ->json();

        return is_array($response) ? $response : [];
    }

    /**
     * Zdroj „akce e-shopu“ — průběžné akce bez letáku, platnost má každá akce vlastní.
     */
    private function eshopLeaflet(): LeafletData
    {
        return new LeafletData(
            kind: LeafletKind::Eshop,
            externalId: config()->string('letaky.sources.kosik.offers_slug'),
            sourceUrl: config()->string('letaky.sources.kosik.offers_page_url'),
        );
    }

    /**
     * Adresa API z konfigurace (základ a cesta).
     */
    private function url(string $pathKey): string
    {
        return config()->string('letaky.sources.kosik.base_url').config()->string("letaky.sources.kosik.{$pathKey}");
    }

    /**
     * Pauza mezi požadavky — ~70 malých stránek po ~70 kB.
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.kosik.request_delay_ms');
    }
}
