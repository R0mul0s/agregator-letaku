<?php

/**
 * Zdroj akční nabídky Kauflandu — stránka nabídky na prodejny.kaufland.cz (ZDROJE_DAT.md).
 *
 * Nabídka se mírně liší po prodejnách (R49): pultové maso, ryby a pár dalších položek.
 * Zdroj stáhne výchozí variantu (bez cookie prodejny) a příští týden, jen když ho stránka
 * aktuálního týdne ohlásí — jinak by parametr `next` vrátil zase aktuální týden. Když jsou
 * uložené seznamy akcí prodejen (ImportStores), stáhne navíc stránky prodejen (cookie
 * `x-aem-variant`) tak, aby měly detail i akce, které ve výchozí variantě chybí — vždy
 * prodejnu s nejvíc chybějícími akcemi, nejvýš `max_store_pages` stránek. Nakonec u akce,
 * která není ve všech prodejnách, vyplní prodejny, kde platí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kaufland;

use App\Domain\Chains\StoreOfferLists;
use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\SourceBatch;
use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;

final class KauflandOfferSource implements OfferSource
{
    private const WEEK_PARAMETER = 'kloffer-week';

    private const CURRENT_WEEK = 'current';

    private const NEXT_WEEK = 'next';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly KauflandOfferParser $parser,
        private readonly StoreOfferLists $storeLists,
    ) {}

    /**
     * Kaufland.
     */
    public function chain(): Chain
    {
        return Chain::Kaufland;
    }

    /**
     * Nabídka aktuálního týdne (a příštího), doplněná o akce prodejen a jejich prodejny.
     */
    public function fetch(): array
    {
        $current = $this->page(self::CURRENT_WEEK);
        $weeks = $current->nextWeekPublished ? [self::CURRENT_WEEK, self::NEXT_WEEK] : [self::CURRENT_WEEK];
        $batches = [$current->batch];
        if ($current->nextWeekPublished) {
            $batches[] = $this->page(self::NEXT_WEEK)->batch;
        }

        $lists = $this->storeLists->fresh(Chain::Kaufland);
        if ($lists === []) {
            return $batches;
        }

        array_push($batches, ...$this->storePages($lists, $this->keys($batches), $weeks));

        return array_map(fn (SourceBatch $batch): SourceBatch => new SourceBatch(
            $batch->leaflet,
            array_map(fn (OfferData $offer): OfferData => $offer->withStoreCodes($this->storeCodes($offer, $lists)), $batch->offers),
            $batch->pages,
        ), $batches);
    }

    /**
     * Stránky prodejen s akcemi, které v dosud stažených stránkách chybí. Vybírá vždy prodejnu,
     * jejíž seznam má nejvíc chybějících akcí; každou nejvýš jednou.
     *
     * @param  array<string, array<string, true>>  $lists  Akce podle prodejny
     * @param  array<string, true>  $known  Klíče akcí, které už mají detail
     * @param  list<string>  $weeks  Zveřejněné týdny
     * @return list<SourceBatch>
     */
    private function storePages(array $lists, array $known, array $weeks): array
    {
        $missing = array_diff_key(array_merge(...array_values($lists)), $known);
        $maxPages = config()->integer('letaky.sources.kaufland.max_store_pages');
        $batches = [];

        while ($missing !== [] && count($batches) < $maxPages) {
            $store = $this->storeWithMostMissing($lists, $missing);
            if ($store === null) {
                break;
            }
            unset($lists[$store]);

            foreach ($weeks as $week) {
                if ($missing === [] || count($batches) >= $maxPages) {
                    break;
                }
                $batches[] = $batch = $this->page($week, $store)->batch;
                $missing = array_diff_key($missing, $this->keys([$batch]));
            }
        }

        return $batches;
    }

    /**
     * Prodejna, jejíž seznam má nejvíc chybějících akcí; null, když žádná žádnou nemá.
     *
     * @param  array<string, array<string, true>>  $lists
     * @param  array<string, true>  $missing
     */
    private function storeWithMostMissing(array $lists, array $missing): ?string
    {
        $best = null;
        $bestCount = 0;
        foreach ($lists as $store => $keys) {
            $count = count(array_intersect_key($missing, $keys));
            if ($count > $bestCount) {
                $best = (string) $store;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * Prodejny, ve kterých akce platí; null = ve všech, nebo ji nezná žádný seznam (seznamy
     * jsou z jiné doby než stránka — neznámou akci neschovávat).
     *
     * @param  array<string, array<string, true>>  $lists
     * @return list<string>|null
     */
    private function storeCodes(OfferData $offer, array $lists): ?array
    {
        $codes = array_keys(array_filter($lists, fn (array $keys): bool => isset($keys[$offer->key()])));
        if ($codes === [] || count($codes) === count($lists)) {
            return null;
        }

        $codes = array_map(strval(...), $codes);
        sort($codes);

        return $codes;
    }

    /**
     * Klíče akcí v dávkách.
     *
     * @param  list<SourceBatch>  $batches
     * @return array<string, true>
     */
    private function keys(array $batches): array
    {
        $keys = [];
        foreach ($batches as $batch) {
            foreach ($batch->offers as $offer) {
                $keys[$offer->key()] = true;
            }
        }

        return $keys;
    }

    /**
     * Stránka nabídky týdne — výchozí, nebo prodejny (cookie).
     */
    private function page(string $week, ?string $store = null): KauflandOfferPage
    {
        $url = config()->string('letaky.sources.kaufland.offers_url');
        $request = $store === null
            ? $this->http->request()
            : $this->http->request(config()->integer('letaky.sources.kaufland.store_page_delay_ms'))
                ->withHeaders(['Cookie' => config()->string('letaky.sources.kaufland.store_cookie').'='.$store]);

        return $this->parser->parse($request->get($url, [self::WEEK_PARAMETER => $week])->body(), $this->weekUrl($url, $week));
    }

    /**
     * Adresa nabídky týdne pro uživatele (odkaz u zdroje a základ odkazu u akce).
     */
    private function weekUrl(string $url, string $week): string
    {
        return $url.'?'.http_build_query([self::WEEK_PARAMETER => $week]);
    }
}
