<?php

/**
 * Nejlepší slevy týdne (R128) — veřejná stránka s akcemi s nejvyšší slevou v ISO týdnu
 * (pondělí–neděle) a archivem po týdnech (`/tyden/2026-41`). Nahoře žebříček napříč obchody
 * (z každého obchodu nejdřív jedna, jen s obrázkem), pod ním nejlepší slevy každého obchodu.
 *
 * Do týdne patří akce, která platí aspoň jeden jeho den (leták st–út je ve dvou týdnech).
 * Aktuální týden ukazuje jen to, co se dá ještě koupit — neskončené a obchodem nestažené akce
 * (R16), i ty, které začnou později v týdnu. Týden, který skončil, je archiv: všechny akce
 * jeho dnů, i skončené, bez akcí stažených obchodem ještě před začátkem týdne. Akce se nemažou
 * (R10), takže se archiv počítá z nich a nic se neukládá; v cache je do dalšího stažení.
 *
 * Archiv začíná týdnem prvního stažení (dřív data nejsou) a obsahuje jen týdny se slevami;
 * aktuální týden má stránku vždy.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Sources\SourceRegistry;
use App\Enums\OfferType;
use App\Models\Offer;
use App\Models\ScrapeRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class WeeklyDeals
{
    /** Routa stránky týdne (`/tyden/2026-41`). */
    public const ROUTE = 'weekly.show';

    /** Routa `/tyden` — přesměruje na aktuální týden. */
    public const INDEX_ROUTE = 'weekly.index';

    /** Parametr routy s týdnem („2026-41“). */
    public const WEEK_PARAMETER = 'week';

    /** Začátek klíčů v cache; za ním druh dat, datum a čas posledního stažení. */
    private const CACHE_KEY = 'weekly';

    /** Jen akce se skutečnou slevou — aspoň jedno procento (jako úvodní stránka). */
    private const MIN_DISCOUNT_PERCENT = 1;

    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly SourceRegistry $sources,
        private readonly OfferPresenter $presenter,
        private readonly OfferPages $pages,
        private readonly DiscountPicker $picker,
    ) {}

    /**
     * Týden, do kterého patří dnešek.
     */
    public function currentWeek(): IsoWeek
    {
        return IsoWeek::containing($this->calendar->today());
    }

    /**
     * Adresa stránky týdne.
     */
    public function url(IsoWeek $week, bool $absolute = false): string
    {
        return route(self::ROUTE, [self::WEEK_PARAMETER => $week->slug()], $absolute);
    }

    /**
     * Týdny se stránkou od nejnovějšího: aktuální vždy, dřívější od týdne prvního stažení,
     * jen se slevami.
     *
     * @return list<IsoWeek>
     */
    public function weeks(): array
    {
        // V cache jen texty — cache nesmí rozbalovat objekty (cache.serializable_classes)
        $slugs = Cache::remember($this->cacheKey('weeks'), $this->ttl(), fn (): array => array_map(
            fn (IsoWeek $week): string => $week->slug(),
            $this->findWeeks(),
        ));

        return array_values(array_filter(array_map(IsoWeek::fromSlug(...), $slugs)));
    }

    /**
     * Má týden stránku?
     */
    public function has(IsoWeek $week): bool
    {
        foreach ($this->weeks() as $available) {
            if ($available->equals($week)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Žebříček napříč obchody a nejlepší slevy po obchodech (data z OfferPresenter::toPage).
     *
     * @return array{top: list<array<string, mixed>>, chainSections: list<array{chain: string, url: string, genitive: string, offers: list<array<string, mixed>>}>}
     */
    public function forWeek(IsoWeek $week): array
    {
        return Cache::remember($this->cacheKey('deals.'.$week->slug()), $this->ttl(), fn (): array => $this->build($week));
    }

    /**
     * Spočítá žebříček a sekce obchodů. Kandidáti žebříčku jsou nejlepší kandidáti každého
     * obchodu — obchod s mnoha vysokými slevami tak nevytlačí ostatní už z výběru.
     *
     * @return array{top: list<array<string, mixed>>, chainSections: list<array{chain: string, url: string, genitive: string, offers: list<array<string, mixed>>}>}
     */
    private function build(IsoWeek $week): array
    {
        $chainLimit = config()->integer('letaky.weekly.chain_offers');
        $factor = config()->integer('letaky.weekly.candidates_factor');
        $sections = [];
        $withImage = [];

        foreach ($this->sources->chainsWithOffers() as $chain) {
            $candidates = $this->discounts($week)
                ->where('chain', $chain)
                ->with('stores')
                ->orderByDiscount()
                ->orderBy('id')
                ->limit($chainLimit * $factor)
                ->get()
                ->all();
            if ($candidates === []) {
                continue;
            }

            $sections[] = [
                'chain' => $chain->value,
                'url' => $this->pages->chainUrl($chain),
                // Obchod ve 2. pádě pro „Všechny akce Lidlu“ — Vue skloňovat neumí
                'genitive' => $chain->genitive(),
                'offers' => $this->present($this->picker->pick($candidates, $chainLimit)),
            ];
            array_push($withImage, ...array_filter($candidates, fn (Offer $offer): bool => $offer->image_url !== null));
        }

        usort($withImage, fn (Offer $a, Offer $b): int => [$b->effectiveDiscountPercent(), $a->id] <=> [$a->effectiveDiscountPercent(), $b->id]);

        return [
            'top' => $this->present($this->picker->pick(
                $withImage,
                config()->integer('letaky.weekly.top_offers'),
                config()->integer('letaky.weekly.top_max_per_chain'),
            )),
            'chainSections' => $sections,
        ];
    }

    /**
     * Dřívější týdny se slevami od týdne prvního stažení a aktuální týden, od nejnovějšího.
     *
     * @return list<IsoWeek>
     */
    private function findWeeks(): array
    {
        $current = $this->currentWeek();
        $weeks = [$current];
        $firstCreated = Offer::query()->min('created_at');
        if (! is_string($firstCreated)) {
            return $weeks;
        }

        // Databáze ukládá čas v UTC (config/app.php)
        $first = IsoWeek::containing($this->calendar->dateOfInstant(CarbonImmutable::parse($firstCreated, 'UTC')));
        for ($week = $current->previous(); ! $week->isBefore($first); $week = $week->previous()) {
            if ($this->discounts($week)->exists()) {
                $weeks[] = $week;
            }
        }

        return $weeks;
    }

    /**
     * Akce týdne se slevou (effectiveDiscountPercent).
     *
     * @return Builder<Offer>
     */
    private function discounts(IsoWeek $week): Builder
    {
        $query = Offer::query()
            ->withoutRaw()
            ->where('offer_type', OfferType::Discount)
            ->withDiscountOf(self::MIN_DISCOUNT_PERCENT)
            ->where('valid_from', '<=', $week->sunday()->toDateString())
            ->where('valid_to', '>=', $week->monday()->toDateString());

        if ($week->equals($this->currentWeek())) {
            // Aktuální týden: jen co se dá ještě koupit
            return $query->active()->notExpired($this->calendar->today());
        }

        // Archiv: akce stažená obchodem až během týdne nebo po něm v týdnu platila
        $weekStart = $this->calendar->startOfDayInstant($week->monday());

        return $query->where(fn (Builder $query) => $query->whereNull('withdrawn_at')->orWhere('withdrawn_at', '>=', $weekStart));
    }

    /**
     * Akce jako data pro stránku.
     *
     * @param  list<Offer>  $offers
     * @return list<array<string, mixed>>
     */
    private function present(array $offers): array
    {
        return array_map(fn (Offer $offer): array => $this->presenter->toPage($offer), $offers);
    }

    /**
     * Klíč v cache: druh dat, dnešní datum (aktuální týden skrývá skončené akce, „Od čt“
     * u budoucích) a konec posledního úspěšného stažení (nové stažení = nový klíč).
     */
    private function cacheKey(string $kind): string
    {
        return implode('.', [
            self::CACHE_KEY,
            $kind,
            $this->calendar->today()->toDateString(),
            ScrapeRun::lastFinishedAt()?->getTimestamp() ?? 0,
        ]);
    }

    /**
     * Nejdelší platnost cache — pojistka pro akce stažené obchodem mezi staženími.
     */
    private function ttl(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMinutes(config()->integer('letaky.weekly.cache_minutes'));
    }
}
