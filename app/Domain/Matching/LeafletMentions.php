<?php

/**
 * Zmínky hlídaných položek na stránkách letáků bez ceny (R27): položka je v letáku, ale cenu
 * k ní neznáme. Zmínka se vynechá, když stejný obchod má k položce akci s cenou ve stejném
 * období — i akci, která už skončila (R107). Vyčleněno z MyOffers (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Offers\LocalCalendar;
use App\Enums\LeafletKind;
use App\Enums\MatchStatus;
use App\Models\FollowedChain;
use App\Models\Leaflet;
use App\Models\LeafletPage;
use App\Models\Offer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class LeafletMentions
{
    public function __construct(
        private TextNormalizer $normalizer,
        private WatchItemMatcher $matcher,
        private LocalCalendar $calendar,
    ) {}

    /**
     * Stránky neskončených letáků sledovaných obchodů, které obsahují aspoň jednu alternativu
     * slova pro předvýběr některé položky; s normalizovaným textem a bez stránek s receptem.
     *
     * @param  Collection<int, FollowedChain>  $followed
     * @param  array<int, WatchRule>  $rules
     * @return list<array{page: LeafletPage, text: string}>
     */
    public function candidatePages(Collection $followed, array $rules): array
    {
        $excluded = array_map(
            fn (mixed $phrase): string => $this->normalizer->normalize((string) $phrase),
            config()->array('letaky.mentions.excluded_page_phrases'),
        );

        $pages = LeafletPage::query()
            ->with('leaflet')
            ->whereHas('leaflet', function (Builder $query) use ($followed): void {
                $query->where('kind', LeafletKind::Leaflet)
                    ->where('valid_to', '>=', $this->calendar->today()->toDateString())
                    ->where(function (Builder $query) use ($followed): void {
                        foreach ($followed as $chain) {
                            $query->orWhere(fn (Builder $query) => $this->whereLeafletFollowed($query, $chain));
                        }
                    });
            })
            ->where(function (Builder $query) use ($rules): void {
                foreach (OfferPrefilter::wordsOf($rules) as $word) {
                    $query->orWhere('text', 'like', OfferPrefilter::likePattern($word));
                }
            })
            ->get()
            ->sortBy(fn (LeafletPage $page): array => [$page->leaflet->valid_from?->toDateString(), $page->leaflet_id, $page->number]);

        $result = [];
        foreach ($pages as $page) {
            $text = $this->normalizer->normalize($page->text);
            if (! array_any($excluded, fn (string $phrase): bool => str_contains($text, $phrase))) {
                $result[] = ['page' => $page, 'text' => $text];
            }
        }

        return $result;
    }

    /**
     * Nejdřívější začátek letáků se zmínkami — od něj se načtou i skončené akce, které zmínku
     * skryjí; bez zmínek dnešek (jen neskončené akce).
     *
     * @param  list<array{page: LeafletPage, text: string}>  $pages
     */
    public function earliestLeafletStart(array $pages, CarbonImmutable $today): CarbonImmutable
    {
        $earliest = $today;
        foreach ($pages as ['page' => $page]) {
            $from = $page->leaflet->valid_from;
            if ($from !== null && $from->lessThan($earliest)) {
                $earliest = $from;
            }
        }

        return $earliest;
    }

    /**
     * Zmínky položky na stránkách letáků, od nejbližšího letáku a po stránkách. Leták, ve kterém
     * má obchod k položce akci s cenou, se přeskočí — zmínka by jen opakovala tutéž akci.
     *
     * @param  list<array{page: LeafletPage, text: string}>  $pages
     * @param  list<Offer>  $offers  Akce s cenou k položce
     * @return list<array{page: LeafletPage, status: MatchStatus}>
     */
    public function forRule(WatchRule $rule, array $pages, array $offers): array
    {
        $mentions = [];
        foreach ($pages as ['page' => $page, 'text' => $text]) {
            $status = $this->matcher->mention($rule, $text);
            if ($status !== null && ! $this->hasPricedOffer($page->leaflet, $offers)) {
                $mentions[] = ['page' => $page, 'status' => $status];
            }
        }

        return $mentions;
    }

    /**
     * Má obchod letáku k položce akci s cenou, která platí (nebo platila) v období letáku?
     *
     * @param  list<Offer>  $offers
     */
    private function hasPricedOffer(Leaflet $leaflet, array $offers): bool
    {
        return array_any($offers, fn (Offer $offer): bool => $offer->chain === $leaflet->chain
            && ($leaflet->valid_to === null || $offer->valid_from <= $leaflet->valid_to)
            && ($leaflet->valid_from === null || $offer->valid_to >= $leaflet->valid_from));
    }

    /**
     * Letáky jednoho sledovaného obchodu podle typu prodejny (leták bez typu platí všude).
     *
     * @param  Builder<Leaflet>  $query
     */
    private function whereLeafletFollowed(Builder $query, FollowedChain $chain): void
    {
        $query->where('chain', $chain->chain);

        if ($chain->store_format !== null) {
            $query->where(fn (Builder $query) => $query->whereNull('format')->orWhere('format', $chain->store_format));
        }
    }
}
