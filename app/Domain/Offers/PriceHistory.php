<?php

/**
 * „Je to opravdu sleva?“ (R59) — porovná cenu akce s dřívějšími akcemi stejné položky
 * u stejného obchodu (obchod + ID položky) za posledních `letaky.price_history.weeks` týdnů.
 * Nabídky se nemažou (R10), historie je tedy v tabulce offers.
 *
 * Akce z PDF letáku mají v ID otisk včetně ceny (`LeafletOfferId`, Albert, Penny) — stejná položka
 * za jinou cenu má jiné ID. U nich je položka obchod + normalizovaný název a balení (R170).
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

use App\Domain\Matching\TextNormalizer;
use App\Domain\Sources\Pdf\LeafletOfferId;
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

    public function __construct(private readonly TextNormalizer $normalizer) {}

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
            $comparison = $this->compare($offer, $earlier[$this->itemKey($offer)] ?? [], $weeks);
            if ($comparison !== null) {
                $result[$offer->id] = $comparison;
            }
        }

        return $result;
    }

    /**
     * Dřívější akce položek jedním dotazem (index chain + external_id), seskupené podle položky
     * (itemKey). Akce z letáku hledá podle názvu — jiná cena je jiné ID.
     *
     * @param  list<Offer>  $offers
     * @return array<string, list<Offer>>
     */
    private function earlierOffers(array $offers, int $weeks): array
    {
        $ids = [];
        $names = [];
        $oldest = null;
        foreach ($offers as $offer) {
            if (self::isLeafletId($offer->external_id)) {
                $names[$offer->chain->value][] = $offer->name;
            } else {
                $ids[$offer->chain->value][] = $offer->external_id;
            }
            $from = $offer->valid_from->subDays($weeks * self::DAYS_IN_WEEK);
            $oldest = $oldest === null || $from->lessThan($oldest) ? $from : $oldest;
        }

        $rows = Offer::query()
            ->where(function (Builder $query) use ($ids, $names): void {
                foreach ($ids as $chain => $externalIds) {
                    $query->orWhere(fn (Builder $query) => $query->where('chain', $chain)->whereIn('external_id', array_values(array_unique($externalIds))));
                }
                foreach ($names as $chain => $chainNames) {
                    $query->orWhere(fn (Builder $query) => $query->where('chain', $chain)
                        ->where('external_id', 'like', LeafletOfferId::PREFIX.'%')
                        ->whereIn('name', array_values(array_unique($chainNames))));
                }
            })
            ->where('valid_to', '>=', $oldest?->toDateString())
            ->get(['id', 'chain', 'external_id', 'name', 'package_text', 'valid_from', 'valid_to', 'price', 'loyalty_price', 'offer_type']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$this->itemKey($row)][] = $row;
        }

        return $grouped;
    }

    /**
     * Totožnost položky obchodu: obchod + ID, u akce z letáku obchod + normalizovaný název a balení.
     */
    private function itemKey(Offer $offer): string
    {
        return self::isLeafletId($offer->external_id)
            ? $offer->chain->value.'|n:'.$this->normalizer->normalize($offer->name, $offer->package_text)
            : $offer->chain->value.'|'.$offer->external_id;
    }

    /**
     * Je ID otisk dlaždice letáku (s cenou)?
     */
    private static function isLeafletId(string $externalId): bool
    {
        return str_starts_with($externalId, LeafletOfferId::PREFIX);
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
     * Akce položky za posledních `letaky.price_history.weeks` týdnů až po konec této akce, pro
     * graf v okně s detailem akce (R163): od nejstarší, i tato akce (`current`). Cena jako
     * u porovnání (comparablePrice), původní cena jen u slevy. Akce na více kusů vynechá.
     *
     * @return list<array{from: string, to: string, price: int, originalPrice: int|null, current: bool}>
     */
    public function timeline(Offer $offer): array
    {
        $since = $offer->valid_from->subDays(config()->integer('letaky.price_history.weeks') * self::DAYS_IN_WEEK);
        $key = $this->itemKey($offer);
        $rows = Offer::query()
            ->where('chain', $offer->chain)
            ->when(
                self::isLeafletId($offer->external_id),
                fn (Builder $query) => $query->where('external_id', 'like', LeafletOfferId::PREFIX.'%')->where('name', $offer->name),
                fn (Builder $query) => $query->where('external_id', $offer->external_id),
            )
            ->where('valid_to', '>=', $since->toDateString())
            ->where('valid_from', '<=', $offer->valid_to->toDateString())
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get(['id', 'chain', 'external_id', 'name', 'package_text', 'valid_from', 'valid_to', 'price', 'original_price', 'loyalty_price', 'offer_type']);

        $points = [];
        foreach ($rows as $row) {
            // Stejný název s jiným balením je jiná položka
            if ($this->itemKey($row) !== $key) {
                continue;
            }
            $price = self::comparablePrice($row);
            if ($price === null) {
                continue;
            }
            $points[] = [
                'from' => $row->valid_from->toDateString(),
                'to' => $row->valid_to->toDateString(),
                'price' => $price,
                'originalPrice' => $row->offer_type === OfferType::Discount ? $row->original_price : null,
                'current' => $row->id === $offer->id,
            ];
        }

        return $points;
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
