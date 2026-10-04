<?php

/**
 * „Je to opravdu sleva?“ (R59) — porovná cenu akce s dřívějšími akcemi stejné položky
 * u stejného obchodu (obchod + ID položky) za posledních `letaky.price_history.weeks` týdnů.
 * Nabídky se nemažou (R10), historie je tedy v tabulce offers.
 *
 * Porovnává se cena bez karty, u akce jen s kartou cena s kartou. Akce na více kusů má jen
 * běžnou cenu kusu, s ní porovnání nedává smysl. Bez dřívější akce se nic neukáže.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\OfferType;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Builder;

final class PriceHistory
{
    /** Akce je nejlevnější za sledované období. */
    public const LOWEST = 'lowest';

    /** Stejně levně už to někdy v období bylo. */
    public const SAME = 'same';

    /** Dřív to bylo levnější — uvede se kdy a za kolik. */
    public const CHEAPER_BEFORE = 'cheaper_before';

    private const DAYS_IN_WEEK = 7;

    /**
     * Porovnání pro každou akci, která má v období dřívější akci; klíč je ID akce.
     *
     * @param  iterable<Offer>  $offers
     * @return array<int, array{status: string, price: int, weeks: int, weeksAgo: int}>
     */
    public function forOffers(iterable $offers): array
    {
        $comparable = [];
        foreach ($offers as $offer) {
            if (self::comparablePrice($offer) !== null) {
                $comparable[] = $offer;
            }
        }
        if ($comparable === []) {
            return [];
        }

        $weeks = config()->integer('letaky.price_history.weeks');
        $earlier = $this->earlierOffers($comparable, $weeks);

        $result = [];
        foreach ($comparable as $offer) {
            $comparison = $this->compare($offer, $earlier[$offer->chain->value.'|'.$offer->external_id] ?? [], $weeks);
            if ($comparison !== null) {
                $result[$offer->id] = $comparison;
            }
        }

        return $result;
    }

    /**
     * Dřívější akce položek jedním dotazem (index chain + external_id), seskupené podle položky.
     *
     * @param  list<Offer>  $offers
     * @return array<string, list<Offer>>
     */
    private function earlierOffers(array $offers, int $weeks): array
    {
        $byChain = [];
        $oldest = null;
        foreach ($offers as $offer) {
            $byChain[$offer->chain->value][] = $offer->external_id;
            $from = $offer->valid_from->subDays($weeks * self::DAYS_IN_WEEK);
            $oldest = $oldest === null || $from->lessThan($oldest) ? $from : $oldest;
        }

        $rows = Offer::query()
            ->where(function (Builder $query) use ($byChain): void {
                foreach ($byChain as $chain => $externalIds) {
                    $query->orWhere(fn (Builder $query) => $query->where('chain', $chain)->whereIn('external_id', array_values(array_unique($externalIds))));
                }
            })
            ->where('valid_to', '>=', $oldest?->toDateString())
            ->get(['id', 'chain', 'external_id', 'valid_from', 'valid_to', 'price', 'loyalty_price', 'offer_type']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->chain->value.'|'.$row->external_id][] = $row;
        }

        return $grouped;
    }

    /**
     * Porovná akci s dřívějšími akcemi položky, které skončily před jejím začátkem a nejdřív
     * před `weeks` týdny; null = žádná taková není.
     *
     * @param  list<Offer>  $earlier
     * @return array{status: string, price: int, weeks: int, weeksAgo: int}|null
     */
    private function compare(Offer $offer, array $earlier, int $weeks): ?array
    {
        $price = self::comparablePrice($offer);
        $since = $offer->valid_from->subDays($weeks * self::DAYS_IN_WEEK);

        $lowest = null;
        foreach ($earlier as $row) {
            $rowPrice = self::comparablePrice($row);
            if ($row->id === $offer->id || $rowPrice === null || ! $row->valid_to->lessThan($offer->valid_from) || $row->valid_to->lessThan($since)) {
                continue;
            }
            if ($lowest === null || $rowPrice < $lowest['price'] || ($rowPrice === $lowest['price'] && $row->valid_to->greaterThan($lowest['at']))) {
                $lowest = ['price' => $rowPrice, 'at' => $row->valid_to];
            }
        }

        if ($lowest === null || $price === null) {
            return null;
        }

        return [
            'status' => match (true) {
                $price < $lowest['price'] => self::LOWEST,
                $price === $lowest['price'] => self::SAME,
                default => self::CHEAPER_BEFORE,
            },
            'price' => $lowest['price'],
            'weeks' => $weeks,
            'weeksAgo' => max(1, (int) ceil($lowest['at']->diffInDays($offer->valid_from) / self::DAYS_IN_WEEK)),
        ];
    }

    /**
     * Cena k porovnání: u akce jen s kartou cena s kartou, u akce na více kusů žádná
     * (má jen běžnou cenu kusu), jinak cena bez karty.
     */
    private static function comparablePrice(Offer $offer): ?int
    {
        return match ($offer->offer_type) {
            OfferType::LoyaltyOnly => $offer->loyalty_price,
            OfferType::Multibuy => null,
            default => $offer->price,
        };
    }
}
