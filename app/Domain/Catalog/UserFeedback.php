<?php

/**
 * Co uživatelé u produktů katalogu skrývají („Tohle ne“, R125) — podklad pro admina k opravě
 * pravidel produktu. Akci, kterou skrylo víc lidí, nejspíš chytají pravidla omylem; slovo,
 * které vylučuje víc lidí, patří mezi vyloučená slova produktu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Matching\TextNormalizer;
use App\Domain\Offers\LocalCalendar;
use App\Models\Offer;
use App\Models\WatchItem;
use App\Models\WatchItemOfferExclusion;
use Illuminate\Database\Eloquent\Builder;

final readonly class UserFeedback
{
    public function __construct(
        private LocalCalendar $calendar,
        private TextNormalizer $normalizer,
    ) {}

    /**
     * Neskončené akce skryté u položek z katalogu: produkt, akce a kolik uživatelů ji skrylo,
     * od nejčastěji skrývané.
     *
     * @param  int|null  $productId  Jen u jednoho produktu
     * @return list<array{productId: int, offer: Offer, users: int}>
     */
    public function hiddenOffers(?int $productId = null): array
    {
        $rows = WatchItemOfferExclusion::query()
            ->join('watch_items', 'watch_items.id', '=', 'watch_item_offer_exclusions.watch_item_id')
            ->whereNotNull('watch_items.product_id')
            ->when($productId !== null, fn (Builder $query) => $query->where('watch_items.product_id', $productId))
            ->whereHas('offer', fn (Builder $query) => $query->active()->notExpired($this->calendar->today()))
            ->groupBy('watch_items.product_id', 'watch_item_offer_exclusions.offer_id')
            ->orderByRaw('COUNT(DISTINCT watch_items.user_id) DESC')
            ->selectRaw('watch_items.product_id AS product_id, watch_item_offer_exclusions.offer_id AS offer_id, COUNT(DISTINCT watch_items.user_id) AS users')
            ->toBase()
            ->get();

        $offers = Offer::query()->withoutRaw()->whereIn('id', $rows->pluck('offer_id')->all())->get()->keyBy('id');

        $hidden = [];
        foreach ($rows as $row) {
            $offer = $offers->get((int) $row->offer_id);
            if ($offer instanceof Offer) {
                $hidden[] = ['productId' => (int) $row->product_id, 'offer' => $offer, 'users' => (int) $row->users];
            }
        }

        return $hidden;
    }

    /**
     * Vlastní vyloučená slova položek z katalogu: produkt, slovo (jak ho napsal první uživatel)
     * a kolik uživatelů ho vylučuje; od nejčastějšího. Slova se porovnávají bez diakritiky.
     *
     * @param  int|null  $productId  Jen u jednoho produktu
     * @return list<array{productId: int, word: string, users: int}>
     */
    public function excludedWords(?int $productId = null): array
    {
        $counts = [];
        WatchItem::query()
            ->whereNotNull('product_id')
            ->whereNotNull('exclude_keywords')
            ->when($productId !== null, fn (Builder $query) => $query->where('product_id', $productId))
            ->orderBy('id')
            ->get(['id', 'product_id', 'exclude_keywords'])
            ->each(function (WatchItem $item) use (&$counts): void {
                foreach (array_unique(preg_split('/[\s|]+/u', (string) $item->exclude_keywords, flags: PREG_SPLIT_NO_EMPTY) ?: []) as $word) {
                    $key = $item->product_id.'|'.$this->normalizer->word($word);
                    $counts[$key] ??= ['productId' => (int) $item->product_id, 'word' => $word, 'users' => 0];
                    $counts[$key]['users']++;
                }
            });

        $rows = array_values($counts);
        usort($rows, fn (array $a, array $b): int => [$b['users'], $a['word']] <=> [$a['users'], $b['word']]);

        return $rows;
    }
}
