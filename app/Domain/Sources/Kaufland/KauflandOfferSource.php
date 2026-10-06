<?php

/**
 * Zdroj akční nabídky Kauflandu — stránka nabídky na prodejny.kaufland.cz (ZDROJE_DAT.md).
 *
 * Nabídka se mírně liší po prodejnách (R49): pultové maso, ryby a pár dalších položek.
 * Zdroj stáhne výchozí variantu (bez cookie prodejny). Stránka aktuálního týdne má od
 * 6. 10. 2026 v datech i ohlášený příští týden; stránku `next` stáhne, jen když ho ohlásí
 * a nemá — bez ohlášení by `next` vrátil zase aktuální týden. Když jsou uložené seznamy akcí
 * prodejen (ImportStores), stáhne navíc stránky prodejen (cookie `x-aem-variant`) tak, aby
 * měly detail i akce, které ve výchozí variantě chybí — vždy prodejnu s nejvíc chybějícími
 * akcemi, nejvýš `max_store_pages` stránek, a z každé si nechá jen chybějící akce. Nakonec
 * u akce, která není ve všech prodejnách, vyplní prodejny, kde platí.
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
        $weeks = $current->nextWeekMissing ? [self::CURRENT_WEEK, self::NEXT_WEEK] : [self::CURRENT_WEEK];
        $batches = $current->batches;
        $seen = $current->itemKeys;
        if ($current->nextWeekMissing) {
            $next = $this->page(self::NEXT_WEEK);
            array_push($batches, ...$next->batches);
            $seen += $next->itemKeys;
        }

        $lists = $this->storeLists->fresh(Chain::Kaufland);
        if ($lists === []) {
            return $batches;
        }

        array_push($batches, ...$this->storePages($lists, $seen, $weeks));

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
     * @param  array<string, true>  $seen  Klíče položek dosud stažených stránek (i přeskočených — jiná stránka je nedoplní)
     * @param  list<string>  $weeks  Zveřejněné týdny
     * @return list<SourceBatch>
     */
    private function storePages(array $lists, array $seen, array $weeks): array
    {
        $missing = array_diff_key(array_merge(...array_values($lists)), $seen);
        $maxPages = config()->integer('letaky.sources.kaufland.max_store_pages');
        $batches = [];
        $pages = 0;

        while ($missing !== [] && $pages < $maxPages) {
            $store = $this->storeWithMostMissing($lists, $missing);
            if ($store === null) {
                break;
            }
            unset($lists[$store]);

            foreach ($weeks as $week) {
                if ($missing === [] || $pages >= $maxPages) {
                    break;
                }
                $pages++;
                $page = $this->page($week, $store);
                foreach ($page->batches as $batch) {
                    $new = $this->onlyMissing($batch, $missing);
                    if ($new->offers !== []) {
                        $batches[] = $new;
                    }
                }
                $missing = array_diff_key($missing, $page->itemKeys);
            }
        }

        return $batches;
    }

    /**
     * Dávka jen s akcemi, které dosud chybí. Ostatní už mají detail z dřívější stránky
     * (import bere první výskyt) — stránka prodejny má celou nabídku obou týdnů (~1 400 akcí)
     * a 40 takových stránek v paměti hosting nezvládl.
     *
     * @param  array<string, true>  $missing
     */
    private function onlyMissing(SourceBatch $batch, array $missing): SourceBatch
    {
        return new SourceBatch(
            $batch->leaflet,
            array_values(array_filter($batch->offers, fn (OfferData $offer): bool => isset($missing[$offer->key()]))),
            $batch->pages,
        );
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
     * Stránka nabídky týdne — výchozí, nebo prodejny (cookie).
     */
    private function page(string $week, ?string $store = null): KauflandOfferPage
    {
        $url = config()->string('letaky.sources.kaufland.offers_url');
        $request = $store === null
            ? $this->http->request()
            : $this->http->request(config()->integer('letaky.sources.kaufland.store_page_delay_ms'))
                ->withHeaders(['Cookie' => config()->string('letaky.sources.kaufland.store_cookie').'='.$store]);

        return $this->parser->parse(
            $request->get($url, [self::WEEK_PARAMETER => $week])->body(),
            $this->weekUrl($url, $week),
            $this->weekUrl($url, self::NEXT_WEEK),
        );
    }

    /**
     * Adresa nabídky týdne pro uživatele (odkaz u zdroje a základ odkazu u akce).
     */
    private function weekUrl(string $url, string $week): string
    {
        return $url.'?'.http_build_query([self::WEEK_PARAMETER => $week]);
    }
}
