<?php

/**
 * Průvodce prvním nastavením nového účtu (R153): co hlídat, kde nakupuje, jaké má karty
 * a jak mu dáme vědět. Podklady pro kroky průvodce (vybrané produkty katalogu po odděleních,
 * počty akcí obchodů a akcí jen s kartou, kolik akcí uživatel s nastavením teď uvidí) a stav
 * pro kartu „Dokončete nastavení“ na Mých slevách.
 *
 * Splněné body karty se poznají ze skutečného stavu účtu, ne z průvodce — co si uživatel
 * nastaví v Hlídám, Mých obchodech nebo účtu, odškrtne se samo. Karta se ukazuje, dokud
 * průvodce nedokončí nebo ji nezavře (`users.onboarded_at`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-18
 */

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Catalog\CatalogBrowseTree;
use App\Domain\Catalog\CategoryPaths;
use App\Domain\Chains\ChainCatalog;
use App\Domain\Matching\MyOffers;
use App\Domain\Offers\LocalCalendar;
use App\Enums\Chain;
use App\Enums\DigestFrequency;
use App\Enums\OfferType;
use App\Models\FollowedChain;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;

final class Onboarding
{
    /** Body karty „Dokončete nastavení“ = kroky průvodce v tomto pořadí. */
    public const STEP_ITEMS = 'items';

    public const STEP_CHAINS = 'chains';

    public const STEP_CARDS = 'cards';

    public const STEP_NOTIFICATIONS = 'notifications';

    public const STEPS = [self::STEP_ITEMS, self::STEP_CHAINS, self::STEP_CARDS, self::STEP_NOTIFICATIONS];

    public function __construct(
        private readonly CategoryPaths $categories,
        private readonly ChainCatalog $chains,
        private readonly LocalCalendar $calendar,
        private readonly MyOffers $myOffers,
    ) {}

    /**
     * Produkty prvního kroku (`letaky.onboarding.products`) po odděleních v pořadí katalogu;
     * produkt, který v katalogu není, se vynechá.
     *
     * @return list<array{department: string, icon: string, productIds: list<int>}>
     */
    public function featuredProducts(): array
    {
        $names = config()->array('letaky.onboarding.products');
        $products = Product::query()->whereIn('name', $names)->get()->keyBy('name');
        $departmentOrder = array_flip(array_keys(config()->array('letaky.catalog.department_icons')));
        $other = __('app.ui.watch.other_department');

        $groups = [];
        foreach ($names as $name) {
            $product = $products->get($name);
            if (! $product instanceof Product) {
                continue;
            }
            $department = $this->categories->department($product->category_id) ?? $other;
            $groups[$department] ??= ['department' => $department, 'icon' => CatalogBrowseTree::icon($department), 'productIds' => []];
            $groups[$department]['productIds'][] = $product->id;
        }
        uasort($groups, fn (array $a, array $b): int => ($departmentOrder[$a['department']] ?? PHP_INT_MAX) <=> ($departmentOrder[$b['department']] ?? PHP_INT_MAX));

        return array_values($groups);
    }

    /**
     * Obchody, které jde sledovat, s počtem akcí, které dnes platí, a kolik z nich je jen
     * s kartou (bez karty je uživatel neuvidí, R19). Počty za celý obchod, bez výběru prodejen.
     *
     * @return list<array{chain: Chain, offers: int, loyaltyOnly: int}>
     */
    public function chainStats(): array
    {
        $today = $this->calendar->today()->toDateString();
        $counts = Offer::query()
            ->active()
            ->where('valid_from', '<=', $today)
            ->where('valid_to', '>=', $today)
            ->selectRaw('chain, count(*) as offers_count, sum(offer_type = ?) as loyalty_only_count', [OfferType::LoyaltyOnly->value])
            ->groupBy('chain')
            ->toBase()
            ->get()
            ->keyBy('chain');

        return array_map(fn (Chain $chain): array => [
            'chain' => $chain,
            'offers' => (int) ($counts->get($chain->value)->offers_count ?? 0),
            'loyaltyOnly' => (int) ($counts->get($chain->value)->loyalty_only_count ?? 0),
        ], $this->chains->available());
    }

    /**
     * Kolik různých akcí, které dnes platí, uživatel s nastavením teď v Mých slevách uvidí.
     */
    public function offersCount(User $user): int
    {
        $ids = [];
        foreach ($this->myOffers->forUser($user, withMentions: false) as $group) {
            foreach ($group['offers'] as $match) {
                $ids[$match['offer']->id] = true;
            }
        }

        return count($ids);
    }

    /**
     * Stav pro kartu „Dokončete nastavení“ na Mých slevách; null = průvodce má za sebou.
     *
     * @return list<array{key: string, done: bool, count: int}>|null
     */
    public function checklist(User $user): ?array
    {
        if ($user->onboarded_at !== null) {
            return null;
        }

        $followed = $user->followedChains()->get();
        $hiddenWithoutCard = $this->hiddenWithoutCard($user, array_values($followed->map(fn (FollowedChain $chain): Chain => $chain->chain)->all()));

        return [
            ['key' => self::STEP_ITEMS, 'done' => $user->watchItems()->exists(), 'count' => $user->watchItems()->count()],
            ['key' => self::STEP_CHAINS, 'done' => $followed->isNotEmpty(), 'count' => $followed->count()],
            // Karty: splněno, když nějakou má, nebo mu bez karty nic neuniká
            ['key' => self::STEP_CARDS, 'done' => ($user->loyalty_programs?->isNotEmpty() ?? false) || $hiddenWithoutCard === 0, 'count' => $hiddenWithoutCard],
            [
                'key' => self::STEP_NOTIFICATIONS,
                'done' => $user->digest_frequency !== DigestFrequency::Off || $user->pushSubscriptions()->exists(),
                'count' => 0,
            ],
        ];
    }

    /**
     * Průvodce je za uživatelem — dokončil ho, nebo zavřel kartu na Mých slevách.
     */
    public function finish(User $user): void
    {
        if ($user->onboarded_at === null) {
            $user->forceFill(['onboarded_at' => CarbonImmutable::now()])->save();
        }
    }

    /**
     * Kolik dnes platných akcí jen s kartou sledovaných obchodů uživatel neuvidí, protože
     * jejich kartu nemá.
     *
     * @param  list<Chain>  $followed
     */
    private function hiddenWithoutCard(User $user, array $followed): int
    {
        $hidden = 0;
        foreach ($this->chainStats() as $stats) {
            $program = $this->chains->loyaltyProgram($stats['chain']);
            if (in_array($stats['chain'], $followed, true) && $program !== null && ! $user->hasLoyaltyProgram($program)) {
                $hidden += $stats['loyaltyOnly'];
            }
        }

        return $hidden;
    }
}
