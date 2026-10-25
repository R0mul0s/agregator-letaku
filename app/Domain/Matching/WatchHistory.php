<?php

/**
 * Historie akcí pro náhled vlastních slov (R104): když položka teď nic nenajde, kdy naposledy
 * byla v akci — nebo že jsme ji za dobu sledování letáků v akci ještě neviděli.
 *
 * Prohledává skončené a obchodem stažené nabídky všech obchodů za okno historie (nabídky se
 * nemažou, R10; okno R113), stejnými pravidly jako Moje slevy: předvýběr nejdelšího slova
 * (OfferPrefilter) a přesné vyhodnocení WatchItemMatcher. Nabídky jdou od nejpozději
 * končících po dávkách, stačí první shoda.
 *
 * Uložená položka bez akce ukáže v Hlídám totéž (R152) — bez akcí, které u ní uživatel skryl
 * („Tohle ne“, R125), a u položky z katalogu bez akcí vyřazených z produktu (R30).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Offers\LocalCalendar;
use App\Models\Offer;
use App\Models\OfferProductExclusion;
use App\Models\WatchItem;
use App\Models\WatchItemOfferExclusion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class WatchHistory
{
    /** Klíč cache pro den prvního stažení — nabídky se nemažou, takže se nemění. */
    private const TRACKING_SINCE_CACHE_KEY = 'offers.tracking_since';

    /** Nabídky z historie se vyhodnocují po dávkách — stačí první shoda. */
    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly WatchItemMatcher $matcher,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Poslední akce, kterou by pravidlo našlo mezi už neplatnými, nebo null.
     *
     * @param  list<int>  $excludedOfferIds  Nabídky, které se nepočítají (skryté nebo vyřazené)
     */
    public function lastSeen(WatchRule $rule, array $excludedOfferIds = []): ?Offer
    {
        if ($rule->keywords === []) {
            return null;
        }

        // Jen okno historie (R113): nabídky se nemažou a LIKE předvýběr index nepoužije — bez
        // okna by náhled při psaní procházel celou historii; po dávkách, stačí první shoda
        $offers = Offer::query()
            ->withoutRaw()
            // Vypnutý obchod (R155) ani v historii
            ->fromEnabledChains()
            ->where('valid_to', '>=', $this->windowStart()->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNotNull('withdrawn_at')
                ->orWhere('valid_to', '<', $this->calendar->today()->toDateString()))
            ->tap(fn (Builder $query) => OfferPrefilter::containingAny($query, $rule->prefilterTerm()))
            ->when($excludedOfferIds !== [], fn (Builder $query) => $query->whereNotIn('id', $excludedOfferIds))
            ->orderByDesc('valid_to')
            ->orderByDesc('id')
            ->lazy(self::CHUNK_SIZE);

        foreach ($offers as $offer) {
            if ($this->matcher->matchPrepared($rule, $offer, $this->matcher->prepare($offer)) !== null) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * Poslední už neplatná akce uložené položky (R152) — její pravidla (u položky z katalogu
     * pravidla produktu s vlastními vyloučenými slovy) bez akcí skrytých u položky a vyřazených
     * z jejího produktu.
     */
    public function lastSeenForItem(WatchItem $item, WatchRule $rule): ?Offer
    {
        $excluded = WatchItemOfferExclusion::query()->where('watch_item_id', $item->id)->pluck('offer_id')->all();
        if ($item->product_id !== null) {
            $excluded = [...$excluded, ...OfferProductExclusion::query()->where('product_id', $item->product_id)->pluck('offer_id')->all()];
        }

        return $this->lastSeen($rule, array_values(array_unique($excluded)));
    }

    /**
     * Poslední místní den, kdy akce platila: konec platnosti, u akce stažené obchodem
     * dřív (R16) den stažení.
     */
    public function endedOn(Offer $offer): CarbonImmutable
    {
        if ($offer->withdrawn_at === null) {
            return $offer->valid_to;
        }

        $withdrawnOn = $this->calendar->startFromInstant($offer->withdrawn_at->toIso8601String());

        return $withdrawnOn->lessThan($offer->valid_to) ? $withdrawnOn : $offer->valid_to;
    }

    /**
     * Místní den, od kterého historii prohledáváme — první uložená nabídka, nejdřív začátek
     * okna historie (lastSeen dál nehledá) — nebo null bez nabídek.
     */
    public function trackingSince(): ?CarbonImmutable
    {
        $since = Cache::rememberForever(self::TRACKING_SINCE_CACHE_KEY, fn (): mixed => Offer::query()->min('created_at'));
        if ($since === null) {
            return null;
        }

        $firstOffer = $this->calendar->startFromInstant((string) $since);

        return $firstOffer->max($this->windowStart());
    }

    /**
     * Nejstarší místní den konce platnosti, který lastSeen prohledá (`letaky.search.preview_history_days`).
     */
    private function windowStart(): CarbonImmutable
    {
        return $this->calendar->today()->subDays(config()->integer('letaky.search.preview_history_days'));
    }
}
