<?php

/**
 * Převod odpovědí JSON API e-shopu Košík.cz na nabídky (ZDROJE_DAT.md, Košík; R179).
 *
 * Pravidla převodu:
 * - ceny jsou float v Kč (`price` akční, `recommendedPrice` nejnižší cena za 30 dní před akcí) — přes
 *   PriceParser::fromFloat; vyšší `recommendedPrice` = sleva s `percentageDiscount` (Košík ho zaokrouhluje
 *   dolů), jinak akční cena (R8)
 * - platnost je jen v textu „Akce platí do 10. 11.“ bez roku a bez začátku: konec se dopočítá k dnešku,
 *   začátek je den stažení — pokračující akce si začátek ponese z prvního stažení (R54)
 * - zboží na kusy vážené (`productQuantity.prefix` „cca“, „Citron, 1 ks“ cca 140 g): `price` je cena
 *   odhadovaného kusu, bere se cena za kg (`pricePerUnit`) a původní cena přepočtená na kg (jako Billa)
 * - všechno jsou akce e-shopu s doručením (`onlineOnly`)
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-08
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kosik;

use App\Domain\Offers\Data\OfferData;
use App\Domain\Offers\Data\PackageSize;
use App\Domain\Offers\Parsing\LeafletDates;
use App\Domain\Offers\Parsing\PackageParser;
use App\Domain\Offers\Parsing\PriceParser;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Offers\Parsing\WebUrl;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use App\Enums\OfferType;
use App\Enums\PackageUnit;
use Carbon\CarbonImmutable;

final class KosikParser
{
    /** Gramů v kilogramu — cena za kg zboží na kusy vážené. */
    private const GRAMS_PER_KG = 1000;

    /** Desetinných míst množství v balení („2,24 kg“). */
    private const QUANTITY_DECIMALS = 3;

    /** Balení, ke kterému se vztahuje cena zboží na kusy vážené. */
    private const PER_KG_PACKAGE = '1 kg';

    public function __construct(
        private readonly PriceParser $prices,
        private readonly PackageParser $packages,
        private readonly LeafletDates $dates,
    ) {}

    /**
     * První stránka oddělení (`page/products/flexible`): celkový počet akcí, položky a kurzor další stránky.
     *
     * @param  array<mixed>  $response
     * @return array{total: int, items: list<array<string, mixed>>, cursor: string|null}
     *
     * @throws SourceResponseChanged
     */
    public function firstPage(array $response, string $slug): array
    {
        $products = is_array($response['products'] ?? null) ? $response['products'] : null;
        $total = $products['totalCount'] ?? null;
        $items = $products['items'] ?? null;

        if (! is_int($total) || ! is_array($items) || ! array_is_list($items)) {
            throw SourceResponseChanged::because(Chain::Kosik, "stránka {$slug} nemá products.totalCount nebo products.items");
        }

        return ['total' => $total, 'items' => $this->items($items), 'cursor' => $this->cursor($products['cursor'] ?? null)];
    }

    /**
     * Další stránka (`products/more`): položky a kurzor další stránky.
     *
     * @param  array<mixed>  $response
     * @return array{items: list<array<string, mixed>>, cursor: string|null}
     *
     * @throws SourceResponseChanged
     */
    public function nextPage(array $response, string $slug): array
    {
        $items = $response['products'] ?? null;

        if (! is_array($items) || ! array_is_list($items)) {
            throw SourceResponseChanged::because(Chain::Kosik, "další stránka {$slug} nemá products");
        }

        return ['items' => $this->items($items), 'cursor' => $this->cursor($response['cursor'] ?? null)];
    }

    /**
     * Prodejce položky (`vendorId`: Košík, lékárna Dr. Max…), null když chybí.
     *
     * @param  array<string, mixed>  $item
     */
    public function vendorId(array $item): ?int
    {
        return is_int($item['vendorId'] ?? null) ? $item['vendorId'] : null;
    }

    /**
     * Skončila akce nedávno (konec před dneškem, popisek se ještě nezměnil)? Taková položka se vynechá,
     * ale nejde o nepřevedenou položku.
     *
     * @param  array<string, mixed>  $item
     */
    public function hasEnded(array $item, CarbonImmutable $today): bool
    {
        return $this->validTo($item, $today)?->lessThan($today) === true;
    }

    /**
     * Nabídka z položky; null bez ID, názvu, ceny nebo čitelného konce akce a u akce, která už skončila.
     *
     * @param  array<string, mixed>  $item
     * @param  CarbonImmutable  $today  Místní dnešek — začátek nově nalezené akce
     */
    public function offer(array $item, CarbonImmutable $today): ?OfferData
    {
        $id = $item['id'] ?? null;
        $name = Text::clean(is_string($item['name'] ?? null) ? $item['name'] : null);
        $price = $this->amount($item['price'] ?? null);
        $validTo = $this->validTo($item, $today);

        if (! is_int($id) || $name === null || $price === null || $validTo === null || $validTo->lessThan($today)) {
            return null;
        }

        $quantity = is_array($item['productQuantity'] ?? null) ? $item['productQuantity'] : [];
        $sizeText = $this->sizeText($quantity);
        $recommended = is_int($item['recommendedPrice'] ?? null) || is_float($item['recommendedPrice'] ?? null) ? (float) $item['recommendedPrice'] : null;
        $originalPrice = $this->amount($recommended);
        $packageText = $sizeText;
        $package = $this->packages->parse($sizeText);
        $description = null;

        $estimatedKg = $this->estimatedWeightKg($quantity, $package);
        $perKg = $this->perKilogram($item);
        if ($estimatedKg !== null) {
            // Cena odhadovaného kusu → cena za kg; balení „cca 140 g“ zůstane v popisu
            if ($perKg === null) {
                return null;
            }
            $price = $perKg;
            $originalPrice = $recommended === null ? null : $this->amount($recommended / $estimatedKg);
            $packageText = self::PER_KG_PACKAGE;
            $package = $this->packages->parse(self::PER_KG_PACKAGE);
            $description = Text::join(is_string($quantity['prefix'] ?? null) ? $quantity['prefix'] : null, $sizeText);
        } elseif ($package === null && $sizeText === null) {
            // Část zboží (ovoce na kusy, drogerie) nemá productQuantity — balení bývá v názvu
            $package = $this->packages->findInText($name);
        }

        $isDiscount = $originalPrice !== null && $originalPrice > $price;
        $percent = $item['percentageDiscount'] ?? null;
        $brand = is_array($item['brand'] ?? null) ? ($item['brand']['name'] ?? null) : null;
        $category = is_array($item['mainCategory'] ?? null) ? ($item['mainCategory']['name'] ?? null) : null;
        $url = $item['url'] ?? null;

        return new OfferData(
            externalId: (string) $id,
            name: $name,
            offerType: $isDiscount ? OfferType::Discount : OfferType::PromoPrice,
            validFrom: $today,
            validTo: $validTo,
            raw: $item,
            price: $price,
            originalPrice: $isDiscount ? $originalPrice : null,
            discountPercent: $isDiscount && is_int($percent) && $percent > 0 ? $percent : null,
            brand: Text::clean(is_string($brand) ? $brand : null),
            description: $description,
            packageText: $packageText,
            package: $package,
            onlineOnly: true,
            sourceCategory: Text::clean(is_string($category) ? $category : null),
            imageUrl: $this->imageUrl($item['image'] ?? null),
            sourceUrl: is_string($url) ? WebUrl::orNull(config()->string('letaky.sources.kosik.base_url').$url) : null,
        );
    }

    /**
     * Konec akce z textu „Akce platí do 10. 11.“ (místní datum včetně); null bez čitelného data. Rok
     * chybí: datum letos, a když už letos minulo o víc než `past_end_tolerance_days`, příští rok
     * („do 5. 1.“ v prosinci). Nedávno minulé datum zůstane — akce skončila (`hasEnded`).
     *
     * @param  array<string, mixed>  $item
     */
    private function validTo(array $item, CarbonImmutable $today): ?CarbonImmutable
    {
        $label = is_string($item['actionLabel'] ?? null) ? $item['actionLabel'] : '';
        if (preg_match(config()->string('letaky.sources.kosik.valid_to_pattern'), $label, $matches) !== 1) {
            return null;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $date = $this->dates->date($day, $month, $today->year);
        if ($date === null || $date->greaterThanOrEqualTo($today)
            || $date->diffInDays($today, true) <= config()->integer('letaky.sources.kosik.past_end_tolerance_days')) {
            return $date;
        }

        return $this->dates->date($day, $month, $today->year + 1);
    }

    /**
     * Hmotnost odhadovaného kusu v kg u zboží na kusy vážené (předpona „cca“ a balení v g nebo kg), jinak null.
     *
     * @param  array<mixed>  $quantity  `productQuantity`
     */
    private function estimatedWeightKg(array $quantity, ?PackageSize $package): ?float
    {
        $prefix = is_string($quantity['prefix'] ?? null) ? mb_strtolower(trim($quantity['prefix'])) : '';
        if ($prefix !== config()->string('letaky.sources.kosik.estimated_weight_prefix') || $package?->unit !== PackageUnit::Gram || $package->quantity <= 0) {
            return null;
        }

        return $package->quantity / self::GRAMS_PER_KG;
    }

    /**
     * Akční cena za kg (`pricePerUnit` s jednotkou kg) v haléřích, jinak null.
     *
     * @param  array<string, mixed>  $item
     */
    private function perKilogram(array $item): ?int
    {
        $perUnit = is_array($item['pricePerUnit'] ?? null) ? $item['pricePerUnit'] : [];

        return ($perUnit['unit'] ?? null) === config()->string('letaky.sources.kosik.kilogram_unit') ? $this->amount($perUnit['price'] ?? null) : null;
    }

    /**
     * Balení „100 g“, „1 l“, „2,24 kg“ z `productQuantity` (bez předpony „cca“); null bez množství.
     *
     * @param  array<mixed>  $quantity
     */
    private function sizeText(array $quantity): ?string
    {
        $value = $quantity['value'] ?? null;
        $unit = $quantity['unit'] ?? null;
        if (! (is_int($value) || is_float($value)) || $value <= 0 || ! is_string($unit)) {
            return null;
        }

        // 100.0 → „100“, 2.24 → „2,24“ — bez koncových nul a desetinné čárky
        $number = rtrim(rtrim(number_format((float) $value, self::QUANTITY_DECIMALS, ',', ''), '0'), ',');

        return Text::join($number, $unit);
    }

    /**
     * Cena v haléřích z čísla v Kč; null, když chybí nebo není kladná.
     */
    private function amount(mixed $crowns): ?int
    {
        if (! is_int($crowns) && ! is_float($crowns)) {
            return null;
        }

        $halers = $this->prices->fromFloat((float) $crowns);

        return $halers > 0 ? $halers : null;
    }

    /**
     * Obrázek v nastavené velikosti — adresa má místo rozměrů zástupný text `WIDTHxHEIGHT`.
     */
    private function imageUrl(mixed $image): ?string
    {
        return is_string($image)
            ? WebUrl::orNull(str_replace(config()->string('letaky.sources.kosik.image_size_placeholder'), config()->string('letaky.sources.kosik.image_size'), $image))
            : null;
    }

    /**
     * Položky výpisu, které jsou objekty.
     *
     * @param  array<mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function items(array $items): array
    {
        return array_values(array_filter($items, is_array(...)));
    }

    /**
     * Kurzor další stránky, null když není.
     */
    private function cursor(mixed $cursor): ?string
    {
        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }
}
