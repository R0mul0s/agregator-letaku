<?php

/**
 * Navázání akcí ze zdroje na už uložené řádky, ať se stejná akce neohlásí znovu jako nová:
 * převzetí řádku uloženého pod předběžným ID (R88) a prodloužení pokračující akce (R54).
 * Vyčleněno z ImportChainOffers (R113).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Offers\Import;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\LocalCalendar;
use App\Enums\Chain;
use App\Models\Offer;

final readonly class OfferContinuity
{
    /** Počet ID v jednom hromadném prodloužení. */
    private const UPDATE_CHUNK = 500;

    public function __construct(private LocalCalendar $calendar) {}

    /**
     * Řádek uložený pod předběžným ID (`OfferData::$supersedes`) převezme ID a platnost akce
     * ze zdroje (R88): Globus ukládá akce budoucího letáku z PDF, a když začnou platit, vrátí
     * je API pod vlastním ID. Upsert pak řádek jen aktualizuje — zůstane jeho `created_at`
     * (souhrn ani centrum upozornění akci neohlásí podruhé jako novou, R74, R76), přiřazení
     * k produktům katalogu i ID v uložených upozorněních. Převezme se jen řádek s překrývající
     * se platností a jen když akce se stejným klíčem ještě uložená není.
     *
     * @param  list<OfferData>  $offers
     */
    public function adoptProvisional(Chain $chain, array $offers): void
    {
        $claims = [];
        foreach ($offers as $offer) {
            if ($offer->supersedes !== null && $offer->supersedes !== $offer->externalId) {
                $claims[$offer->supersedes][] = $offer;
            }
        }
        if ($claims === []) {
            return;
        }

        $provisional = Offer::query()
            ->where('chain', $chain)
            ->whereIn('external_id', array_keys($claims))
            ->get(['id', 'external_id', 'valid_from', 'valid_to']);
        if ($provisional->isEmpty()) {
            return;
        }

        // Akce, které už jsou uložené pod ID ze zdroje — ty žádný řádek nepřevezmou
        $targets = [];
        foreach ($provisional as $row) {
            foreach ($claims[$row->external_id] as $offer) {
                $targets[$offer->externalId] = true;
            }
        }
        $existing = Offer::query()
            ->where('chain', $chain)
            ->whereIn('external_id', array_map(strval(...), array_keys($targets)))
            ->get(['external_id', 'valid_from', 'valid_to'])
            ->mapWithKeys(fn (Offer $row): array => [OfferData::keyOf($row->external_id, $row->valid_from, $row->valid_to) => true])
            ->all();

        foreach ($provisional as $row) {
            foreach ($claims[$row->external_id] as $index => $offer) {
                if (isset($existing[$offer->key()]) || $row->valid_from->greaterThan($offer->validTo) || $row->valid_to->lessThan($offer->validFrom)) {
                    continue;
                }

                Offer::query()->whereKey($row->id)->update([
                    'external_id' => $offer->externalId,
                    'valid_from' => $offer->validFrom->toDateString(),
                    'valid_to' => $offer->validTo->toDateString(),
                ]);
                $existing[$offer->key()] = true;
                unset($claims[$row->external_id][$index]);

                break;
            }
        }
    }

    /**
     * Akce, která navazuje na uloženou akci se stejnou cenou, převezme její začátek platnosti,
     * takže upsert prodlouží existující řádek místo založení nového (R54). Jinak by pokračující
     * akce každý týden dostala nové ID — souhrn by ji poslal jako novou a ruční opravy katalogu
     * by se ztratily. Jen u zdrojů, které platnost samy odvozují (Billa: akční týden, R48);
     * u ostatních by se slily skutečně odlišné akce. Akce, které ještě nezačaly, se neprodlužují (R89).
     *
     * @param  list<OfferData>  $offers
     * @return list<OfferData>
     */
    public function continuePrevious(Chain $chain, array $offers): array
    {
        if ($offers === [] || config("letaky.sources.{$chain->value}.extends_continuing_offers") !== true) {
            return $offers;
        }

        $previous = Offer::query()
            ->where('chain', $chain)
            ->active()
            ->whereIn('external_id', array_values(array_unique(array_map(fn (OfferData $offer): string => $offer->externalId, $offers))))
            ->get(['id', 'external_id', 'valid_from', 'valid_to', 'price', 'loyalty_price'])
            ->groupBy('external_id');

        $today = $this->calendar->today();
        $result = [];
        $extended = [];
        foreach ($offers as $offer) {
            // Akce, která ještě nezačala (Billa: PDF letáku dalšího týdne, R89), nic neprodlužuje —
            // pokračování pozná zdroj sám a prodlouží ho až akce z API v novém týdnu
            $row = $offer->validFrom->greaterThan($today) ? null
                : $previous->get($offer->externalId)?->first(fn (Offer $row): bool => $this->continues($row, $offer));
            if ($row instanceof Offer) {
                if ($row->valid_to->toDateString() !== $offer->validTo->toDateString()) {
                    $extended[$offer->validTo->toDateString()][] = $row->id;
                }
                $offer = $offer->withValidFrom($row->valid_from);
            }
            $result[] = $offer;
        }

        foreach ($extended as $validTo => $ids) {
            foreach (array_chunk($ids, self::UPDATE_CHUNK) as $chunk) {
                Offer::query()->whereIn('id', $chunk)->update(['valid_to' => $validTo]);
            }
        }

        return $result;
    }

    /**
     * Navazuje nabídka na uloženou akci? Uložená začala dřív, skončila nejdřív den před
     * začátkem nové a nejpozději s ní a má stejnou cenu (změna ceny = nová akce).
     */
    private function continues(Offer $row, OfferData $offer): bool
    {
        return $row->valid_from->lessThan($offer->validFrom)
            && $row->valid_to->greaterThanOrEqualTo($offer->validFrom->subDay())
            && $row->valid_to->lessThanOrEqualTo($offer->validTo)
            && $row->price === $offer->price
            && $row->loyalty_price === $offer->loyaltyPrice;
    }
}
