<?php

/**
 * Čisté adresy výpisu akcí pro vyhledávače (R94): obchod `/akce/lidl` a produkt katalogu
 * `/akce/pivo` místo `/akce?chain=lidl` a `/akce?produkt=12`. Skládá adresu z filtrů
 * (OfferFilters) a převádí část adresy zpět na obchod nebo produkt.
 *
 * Část adresy produktu se skládá z jeho názvu („Minerální voda“ → `mineralni-voda`), v databázi
 * uložená není — katalog má pár set produktů a přejmenování je vzácné (stará adresa pak vrátí
 * 404). Shoda s obchodem, rezervovanou cestou nebo jiným produktem dostane na konec ID produktu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\Chain;
use App\Models\OfferProduct;
use App\Models\Product;
use Illuminate\Support\Str;

final class OfferPages
{
    /** Cesty pod /akce/, které nejsou obchod ani produkt (routes/web.php). */
    private const RESERVED_SLUGS = ['naseptavac'];

    /** Název routy výpisu s částí adresy (obchod nebo produkt). */
    public const PAGE_ROUTE = 'offers.page';

    /** @var array<int, array{slug: string, name: string}>|null Produkty podle ID, načtené jednou za požadavek */
    private ?array $products = null;

    public function __construct(private readonly LocalCalendar $calendar) {}

    /**
     * Obchod nebo produkt podle části adresy; null = taková stránka není.
     *
     * @return array{chain: Chain|null, productId: int|null}|null
     */
    public function resolve(string $slug): ?array
    {
        $chain = Chain::tryFrom($slug);
        if ($chain !== null) {
            return ['chain' => $chain, 'productId' => null];
        }

        foreach ($this->products() as $id => $product) {
            if ($product['slug'] === $slug) {
                return ['chain' => null, 'productId' => $id];
            }
        }

        return null;
    }

    /**
     * Adresa výpisu s filtry: produkt jako `/akce/pivo`, jeden obchod jako `/akce/lidl`,
     * jinak `/akce?…`. Ostatní filtry a `$extra` (hledání, stránka) jako parametry.
     *
     * @param  array<string, int|string|null>  $extra
     */
    public function url(OfferFilters $filters, array $extra = [], bool $absolute = false): string
    {
        $parameters = [...$filters->urlParameters(), ...array_filter($extra, fn (int|string|null $value): bool => $value !== null && $value !== '')];
        $slug = $filters->productId !== null ? $this->productSlug($filters->productId) : null;

        if ($slug !== null) {
            unset($parameters[OfferFilters::PRODUCT_PARAMETER]);
        } elseif (count($filters->chains) === 1) {
            $slug = $filters->chains[0]->value;
            unset($parameters[OfferFilters::CHAIN_PARAMETER]);
        }

        return $slug === null
            ? route('offers', $parameters, $absolute)
            : route(self::PAGE_ROUTE, ['slug' => $slug, ...$parameters], $absolute);
    }

    /**
     * Adresa akcí jednoho obchodu (`/akce/lidl`).
     */
    public function chainUrl(Chain $chain, bool $absolute = false): string
    {
        return $this->url(new OfferFilters([$chain]), absolute: $absolute);
    }

    /**
     * Adresa akcí produktu katalogu (`/akce/pivo`).
     */
    public function productUrl(int $productId, bool $absolute = false): string
    {
        return $this->url(new OfferFilters(productId: $productId), absolute: $absolute);
    }

    /**
     * Název produktu katalogu, null = neexistuje.
     */
    public function productName(int $productId): ?string
    {
        return $this->products()[$productId]['name'] ?? null;
    }

    /**
     * ID produktů, které mají dnes neskončené akce (sitemap, odkazy pro roboty), podle názvu.
     *
     * @return list<int>
     */
    public function productsWithOffers(): array
    {
        $ids = OfferProduct::query()
            ->join('offers', 'offers.id', '=', 'offer_product.offer_id')
            ->whereNull('offers.withdrawn_at')
            ->whereDate('offers.valid_to', '>=', $this->calendar->today()->toDateString())
            ->distinct()
            ->pluck('offer_product.product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $names = array_map(fn (array $product): string => $product['name'], array_intersect_key($this->products(), array_flip($ids)));
        asort($names);

        return array_keys($names);
    }

    /**
     * Část adresy produktu, null = produkt neexistuje.
     */
    private function productSlug(int $productId): ?string
    {
        return $this->products()[$productId]['slug'] ?? null;
    }

    /**
     * Produkty katalogu s částí adresy; shody (obchod, rezervovaná cesta, stejný název)
     * dostanou na konec ID — pořadí podle ID, aby se adresa starších produktů neměnila.
     *
     * @return array<int, array{slug: string, name: string}>
     */
    private function products(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $taken = array_flip([...self::RESERVED_SLUGS, ...array_map(fn (Chain $chain): string => $chain->value, Chain::cases())]);
        $this->products = [];
        foreach (Product::query()->orderBy('id')->pluck('name', 'id') as $id => $name) {
            $slug = Str::slug((string) $name);
            if ($slug === '' || isset($taken[$slug])) {
                $slug = trim($slug.'-'.$id, '-');
            }
            $taken[$slug] = true;
            $this->products[(int) $id] = ['slug' => $slug, 'name' => (string) $name];
        }

        return $this->products;
    }
}
