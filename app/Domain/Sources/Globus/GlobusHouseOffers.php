<?php

/**
 * Akce z katalogů všech hypermarketů Globusu sloučené po položce a ceně (R131).
 *
 * Akce (`vanr`) se stejnou cenou, původní cenou a cenou s aplikací je jedna nabídka; hypermarkety
 * s jinou cenou dostanou vlastní nabídku. ID bez přípony má skupina s výchozím hypermarketem
 * (`house_id`, dřív jediný stahovaný — uložené akce navazují), jinak ta s nejvíc hypermarkety;
 * ostatní skupiny mají ID s cenami („00689137004-3290-5190-0“), aby se jejich řádky nepřepisovaly.
 * Platnost skupiny je od nejdřívějšího začátku do nejpozdějšího konce — katalogy hypermarketů se
 * liší o den (`priceValidFrom` 6. 10. × 7. 10.). Nabídka ve všech hypermarketech platí všude
 * (prodejny null), jinak jen v hypermarketech své skupiny (R49).
 *
 * Instance sbírá akce jednoho stažení — každé stažení si vytvoří novou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Sources\Globus;

use App\Domain\Offers\Data\OfferData;

final class GlobusHouseOffers
{
    /** Oddělovač ID položky a cen u skupiny, která nemá ID bez přípony. */
    private const VARIANT_SEPARATOR = '-';

    /** Chybějící cena v ID skupiny (bez původní ceny, bez ceny s aplikací). */
    private const NO_PRICE = 0;

    /**
     * Skupiny podle ID položky a cen: první nabídka skupiny a hypermarkety, kde platí.
     *
     * @var array<string, array<string, array{offer: OfferData, houses: array<string, true>}>>
     */
    private array $groups = [];

    /**
     * @param  string  $referenceHouse  Výchozí hypermarket — jeho skupina má ID bez přípony
     */
    public function __construct(private readonly string $referenceHouse) {}

    /**
     * Přidá akci z katalogu hypermarketu.
     */
    public function add(string $house, OfferData $offer): void
    {
        $prices = $this->prices($offer);
        $group = $this->groups[$offer->externalId][$prices] ?? null;

        if ($group === null) {
            $this->groups[$offer->externalId][$prices] = ['offer' => $offer, 'houses' => [$house => true]];

            return;
        }

        $merged = $group['offer'];
        if ($offer->validFrom->lessThan($merged->validFrom)) {
            $merged = $merged->withValidFrom($offer->validFrom);
        }
        if ($offer->validTo->greaterThan($merged->validTo)) {
            $merged = $merged->withValidTo($offer->validTo);
        }
        $this->groups[$offer->externalId][$prices] = ['offer' => $merged, 'houses' => $group['houses'] + [$house => true]];
    }

    /**
     * Sloučené nabídky s ID skupiny a prodejnami, kde platí.
     *
     * @param  list<string>  $allHouses  Všechny stažené hypermarkety — akce ve všech platí všude
     * @return list<OfferData>
     */
    public function offers(array $allHouses): array
    {
        $offers = [];
        foreach ($this->groups as $id => $groups) {
            $primary = $this->primaryGroup($groups);
            foreach ($groups as $prices => $group) {
                $houses = array_map(strval(...), array_keys($group['houses']));
                sort($houses);
                $storeCodes = count($houses) === count($allHouses) ? null : $houses;
                $offer = $prices === $primary ? $group['offer'] : $group['offer']->withExternalId($id.self::VARIANT_SEPARATOR.$prices);
                $offers[] = $offer->withStoreCodes($storeCodes);
            }
        }

        return $offers;
    }

    /**
     * Skupina, která dostane ID bez přípony: s výchozím hypermarketem, jinak s nejvíc
     * hypermarkety (při shodě ta, která je dřív — pořadí stažení).
     *
     * @param  array<string, array{offer: OfferData, houses: array<string, true>}>  $groups
     */
    private function primaryGroup(array $groups): string
    {
        $primary = null;
        $primaryCount = 0;
        foreach ($groups as $prices => $group) {
            if (isset($group['houses'][$this->referenceHouse])) {
                return (string) $prices;
            }
            if (count($group['houses']) > $primaryCount) {
                $primary = (string) $prices;
                $primaryCount = count($group['houses']);
            }
        }

        return $primary ?? (string) array_key_first($groups);
    }

    /**
     * Ceny nabídky jako část ID skupiny: cena, původní cena a cena s aplikací v haléřích.
     */
    private function prices(OfferData $offer): string
    {
        return implode(self::VARIANT_SEPARATOR, [
            $offer->price ?? self::NO_PRICE,
            $offer->originalPrice ?? self::NO_PRICE,
            $offer->loyaltyPrice ?? self::NO_PRICE,
        ]);
    }
}
