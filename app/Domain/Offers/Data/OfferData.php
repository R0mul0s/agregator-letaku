<?php

/**
 * Nabídka ze zdroje obchodu v jednotném tvaru — výstup zdroje, vstup pro uložení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers\Data;

use App\Enums\LoyaltyProgram;
use App\Enums\OfferType;
use App\Enums\StoreFormat;
use Carbon\CarbonImmutable;

final readonly class OfferData
{
    /**
     * @param  int|null  $price  Haléře; cena bez karty, null = obchod ji neuvádí (jen cena s kartou)
     * @param  int|null  $originalPrice  Haléře; původní cena — jen u typu Discount
     * @param  int|null  $loyaltyPrice  Haléře; cena s kartou nebo aplikací
     * @param  CarbonImmutable  $validFrom  Místní datum (R7)
     * @param  CarbonImmutable  $validTo  Místní datum včetně (R7)
     * @param  array<string, mixed>  $raw  Původní položka od obchodu
     * @param  list<string>|null  $storeCodes  Prodejny, ve kterých akce platí (R49); null = všechny
     * @param  string|null  $supersedes  Předběžné ID stejné akce uložené dřív z jiného zdroje (Globus: PDF letáku
     *                                   před začátkem platnosti, R88) — import ten řádek převezme
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public OfferType $offerType,
        public CarbonImmutable $validFrom,
        public CarbonImmutable $validTo,
        public array $raw,
        public ?int $price = null,
        public ?int $originalPrice = null,
        public ?int $loyaltyPrice = null,
        public ?LoyaltyProgram $loyaltyProgram = null,
        public ?int $discountPercent = null,
        public ?string $promotionText = null,
        public ?string $brand = null,
        public ?string $description = null,
        public ?string $variantNote = null,
        public ?string $packageText = null,
        public ?PackageSize $package = null,
        public bool $onlineOnly = false,
        public ?StoreFormat $storeFormat = null,
        public ?string $sourceCategory = null,
        public ?string $imageUrl = null,
        public ?string $sourceUrl = null,
        public ?array $storeCodes = null,
        public ?string $supersedes = null,
    ) {}

    /**
     * Kopie s dostupností — kde nabídka platí (formát prodejny) a jestli jen v e-shopu (R4).
     */
    public function withAvailability(bool $onlineOnly, ?StoreFormat $storeFormat): self
    {
        return new self(
            externalId: $this->externalId,
            name: $this->name,
            offerType: $this->offerType,
            validFrom: $this->validFrom,
            validTo: $this->validTo,
            raw: $this->raw,
            price: $this->price,
            originalPrice: $this->originalPrice,
            loyaltyPrice: $this->loyaltyPrice,
            loyaltyProgram: $this->loyaltyProgram,
            discountPercent: $this->discountPercent,
            promotionText: $this->promotionText,
            brand: $this->brand,
            description: $this->description,
            variantNote: $this->variantNote,
            packageText: $this->packageText,
            package: $this->package,
            onlineOnly: $onlineOnly,
            storeFormat: $storeFormat,
            sourceCategory: $this->sourceCategory,
            imageUrl: $this->imageUrl,
            sourceUrl: $this->sourceUrl,
            storeCodes: $this->storeCodes,
            supersedes: $this->supersedes,
        );
    }

    /**
     * Kopie s prodejnami, ve kterých akce platí (R49); null = všechny prodejny.
     *
     * @param  list<string>|null  $storeCodes
     */
    public function withStoreCodes(?array $storeCodes): self
    {
        return new self(...[...get_object_vars($this), 'storeCodes' => $storeCodes]);
    }

    /**
     * Kopie s jiným začátkem platnosti — akce navazující na uloženou akci (R54).
     */
    public function withValidFrom(CarbonImmutable $validFrom): self
    {
        return new self(...[...get_object_vars($this), 'validFrom' => $validFrom]);
    }

    /**
     * Kopie s jiným koncem platnosti — sloučená akce hypermarketů Globusu (R131).
     */
    public function withValidTo(CarbonImmutable $validTo): self
    {
        return new self(...[...get_object_vars($this), 'validTo' => $validTo]);
    }

    /**
     * Kopie s jiným ID — akce Globusu s jinou cenou v části hypermarketů (R131).
     */
    public function withExternalId(string $externalId): self
    {
        return new self(...[...get_object_vars($this), 'externalId' => $externalId]);
    }

    /**
     * Klíč pro deduplikaci — odpovídá unikátnímu indexu tabulky offers (bez obchodu, ten má celá dávka společný).
     */
    public function key(): string
    {
        return self::keyOf($this->externalId, $this->validFrom, $this->validTo);
    }

    /**
     * Klíč nabídky z ID položky a platnosti — stejný pro data ze zdroje i uložené řádky.
     */
    public static function keyOf(string $externalId, CarbonImmutable $validFrom, CarbonImmutable $validTo): string
    {
        return $externalId.'|'.$validFrom->toDateString().'|'.$validTo->toDateString();
    }
}
