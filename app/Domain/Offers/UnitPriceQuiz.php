<?php

/**
 * Hra „Co je levnější?“ na úvodní stránce (R90) — dvojice dnes platných akcí stejného produktu
 * katalogu z různých obchodů a návštěvník hádá, která vyjde levněji za kilo nebo litr. Hravá
 * ukázka řazení podle ceny za jednotku: přednost mají dvojice, kde je levnější za kilo
 * dražší balení (velký nápis AKCE by návštěvníka oblafnul).
 *
 * Dvojice se mění jednou za den (náhoda se semínkem z data) — stejná stránka během dne
 * nepřeskakuje mezi různými hrami.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\OfferType;
use App\Enums\PackageUnit;
use App\Models\Offer;
use App\Models\OfferProduct;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class UnitPriceQuiz
{
    public function __construct(
        private readonly OfferPresenter $presenter,
        private readonly LocalCalendar $calendar,
    ) {}

    /**
     * Kola hry: produkt, dvě akce (pořadí náhodné) a ID té levnější za jednotku.
     *
     * @return list<array{product: string, offers: list<array<string, mixed>>, cheaperId: int}>
     */
    public function rounds(): array
    {
        $today = $this->calendar->today();
        $randomizer = new Randomizer(new Mt19937(crc32($today->toDateString())));

        $productIds = $this->candidateProducts();
        $rowsByProduct = [];
        foreach ($this->eligibleOffers()->whereIn('offer_product.product_id', $productIds)->get() as $row) {
            $rowsByProduct[(int) $row->product_id][] = $row;
        }

        $pairs = [];
        $usedOffers = [];
        // Seřadit před zamícháním — pořadí řádků z databáze není zaručené a hra se má během dne držet
        ksort($rowsByProduct);
        foreach ($randomizer->shuffleArray(array_keys($rowsByProduct)) as $productId) {
            $pair = $this->pair($rowsByProduct[$productId], $usedOffers);
            if ($pair === null) {
                continue;
            }
            $pairs[] = $randomizer->shuffleArray($pair);
            $usedOffers[$pair[0]['id']] = true;
            $usedOffers[$pair[1]['id']] = true;
            if (count($pairs) >= config()->integer('letaky.landing.quiz_rounds')) {
                break;
            }
        }

        $offers = Offer::query()
            ->whereIn('id', array_keys($usedOffers))
            ->with('stores')
            ->get()
            ->keyBy('id');

        $rounds = [];
        foreach ($pairs as [$first, $second]) {
            $a = $offers->get($first['id']);
            $b = $offers->get($second['id']);
            if (! $a instanceof Offer || ! $b instanceof Offer) {
                continue;
            }
            $rounds[] = [
                'product' => $first['product'],
                'offers' => [$this->presenter->toPage($a), $this->presenter->toPage($b)],
                'cheaperId' => $first['unitPrice'] < $second['unitPrice'] ? $first['id'] : $second['id'],
            ];
        }

        return $rounds;
    }

    /**
     * Produkty s nejvíc dnes platnými akcemi s cenou za kilo nebo litr.
     *
     * @return list<int>
     */
    private function candidateProducts(): array
    {
        return array_values($this->eligibleOffers()
            ->reorder()
            ->select('offer_product.product_id')
            ->groupBy('offer_product.product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('offer_product.product_id')
            ->limit(config()->integer('letaky.landing.quiz_candidate_products'))
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Dnes platné akce produktů katalogu s běžnou cenou, balením v gramech nebo mililitrech
     * a obrázkem — jen sloupce pro výběr dvojice. Akce na více kusů a jen s kartou ne: cena
     * za jednotku by neseděla s cenou na kartě.
     */
    private function eligibleOffers(): QueryBuilder
    {
        $today = $this->calendar->today()->toDateString();
        $units = array_values(array_filter(
            PackageUnit::cases(),
            fn (PackageUnit $unit): bool => in_array($unit->unitPriceKey(), config()->array('letaky.landing.quiz_units'), true),
        ));

        return OfferProduct::query()
            ->join('offers', 'offers.id', '=', 'offer_product.offer_id')
            ->join('products', 'products.id', '=', 'offer_product.product_id')
            ->whereNull('offers.withdrawn_at')
            ->where('offers.valid_from', '<=', $today)
            ->where('offers.valid_to', '>=', $today)
            ->whereNotNull('offers.price')
            ->whereNotNull('offers.quantity')
            ->whereNotNull('offers.image_url')
            ->whereIn('offers.unit', $units)
            ->whereIn('offers.offer_type', [OfferType::Discount, OfferType::PromoPrice])
            ->select(['offer_product.product_id', 'products.name AS product_name', 'offers.id', 'offers.chain', 'offers.price', 'offers.quantity', 'offers.unit'])
            ->toBase();
    }

    /**
     * Dvojice akcí produktu z různých obchodů se stejnou jednotkou a cenou za jednotku lišící
     * se aspoň o nastavené procento. Přednost má dvojice, kde je levnější za jednotku
     * dražší balení; jinak ta s nejmenším (ještě dost velkým) rozdílem — hádání má být těžké.
     *
     * @param  list<\stdClass>  $rows
     * @param  array<int, true>  $usedOffers
     * @return array{0: array{id: int, product: string, unitPrice: int}, 1: array{id: int, product: string, unitPrice: int}}|null
     */
    private function pair(array $rows, array $usedOffers): ?array
    {
        $candidates = [];
        foreach ($rows as $row) {
            $unit = PackageUnit::tryFrom((string) $row->unit);
            $unitPrice = UnitPrice::of((int) $row->price, (float) $row->quantity, $unit);
            if ($unit === null || $unitPrice === null || $unitPrice <= 0 || isset($usedOffers[(int) $row->id])) {
                continue;
            }
            $candidates[] = [
                'id' => (int) $row->id,
                'product' => (string) $row->product_name,
                'chain' => (string) $row->chain,
                'unitKey' => $unit->unitPriceKey(),
                'price' => (int) $row->price,
                'unitPrice' => $unitPrice,
            ];
        }

        $minDifference = config()->integer('letaky.landing.quiz_min_difference_percent');
        $best = null;
        $bestKey = null;
        foreach ($candidates as $i => $a) {
            foreach (array_slice($candidates, $i + 1) as $b) {
                if ($a['chain'] === $b['chain'] || $a['unitKey'] !== $b['unitKey'] || $a['price'] === $b['price']) {
                    continue;
                }
                [$cheaper, $dearer] = $a['unitPrice'] < $b['unitPrice'] ? [$a, $b] : [$b, $a];
                $difference = intdiv(($dearer['unitPrice'] - $cheaper['unitPrice']) * 100, $cheaper['unitPrice']);
                if ($difference < $minDifference) {
                    continue;
                }
                // Past (levnější za kilo stojí víc) první, pak nejmenší rozdíl, pak ID kvůli stálosti
                $key = [$cheaper['price'] > $dearer['price'] ? 0 : 1, $difference, $cheaper['id'], $dearer['id']];
                if ($bestKey === null || $key < $bestKey) {
                    $best = [$cheaper, $dearer];
                    $bestKey = $key;
                }
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            ['id' => $best[0]['id'], 'product' => $best[0]['product'], 'unitPrice' => $best[0]['unitPrice']],
            ['id' => $best[1]['id'], 'product' => $best[1]['product'], 'unitPrice' => $best[1]['unitPrice']],
        ];
    }
}
