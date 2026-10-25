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
 * Prodejny obchodů, kde se akce po prodejnách liší (Kaufland, Globus — `store_pages`), mají
 * vlastní stránku `/akce/kaufland/trutnov` (R172): obchod a jedna prodejna. Část adresy je
 * z názvu prodejny, shoda v rámci obchodu dostane na konec kód prodejny.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Domain\Chains\ChainCatalog;
use App\Enums\Chain;
use App\Models\OfferProduct;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Str;

final class OfferPages
{
    /** Cesty pod /akce/, které nejsou obchod ani produkt (routes/web.php). */
    private const RESERVED_SLUGS = ['naseptavac'];

    /** Název routy výpisu s částí adresy (obchod nebo produkt). */
    public const PAGE_ROUTE = 'offers.page';

    /** Název routy stránky prodejny `/akce/kaufland/trutnov` (R172). */
    public const STORE_ROUTE = 'offers.store';

    /** Části adresy pod `/akce/{obchod}/`, které nejsou prodejna (kanál RSS, R149). */
    public const RESERVED_STORE_SLUGS = [OfferFeed::PATH_SUFFIX];

    /** @var array<int, array{slug: string, name: string}>|null Produkty podle ID, načtené jednou za požadavek */
    private ?array $products = null;

    /** @var array<string, array<string, array{slug: string, name: string, city: string}>>|null Prodejny se stránkou: obchod => kód => … */
    private ?array $stores = null;

    public function __construct(
        private readonly LocalCalendar $calendar,
        private readonly ChainCatalog $chains,
    ) {}

    /**
     * Obchod nebo produkt podle části adresy; null = taková stránka není.
     *
     * @return array{chain: Chain|null, productId: int|null}|null
     */
    public function resolve(string $slug): ?array
    {
        $chain = Chain::tryFrom($slug);
        if ($chain !== null) {
            // Vypnutý obchod (R155) stránku nemá — 404, vyhledávač ji vyřadí
            return $chain->isDisabled() ? null : ['chain' => $chain, 'productId' => null];
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

        // Jedna prodejna obchodu se stránkou (R172) — /akce/kaufland/trutnov
        $store = $this->storeOf($filters);
        if ($store !== null) {
            unset($parameters[OfferFilters::CHAIN_PARAMETER], $parameters[OfferFilters::STORES_PARAMETER]);

            return route(self::STORE_ROUTE, ['slug' => $store['chain']->value, 'store' => $store['slug'], ...$parameters], $absolute);
        }

        $slug = $this->slug($filters);

        if ($slug !== null) {
            unset($parameters[$this->isProductSlug($filters) ? OfferFilters::PRODUCT_PARAMETER : OfferFilters::CHAIN_PARAMETER]);
        }

        return $slug === null
            ? route('offers', $parameters, $absolute)
            : route(self::PAGE_ROUTE, ['slug' => $slug, ...$parameters], $absolute);
    }

    /**
     * Část adresy čisté stránky: produkt (`pivo`), jinak jeden obchod (`lidl`); null = výpis
     * bez čisté adresy (víc obchodů, žádný, nebo neexistující produkt).
     */
    public function slug(OfferFilters $filters): ?string
    {
        if ($this->isProductSlug($filters)) {
            return $this->productSlug((int) $filters->productId);
        }

        return count($filters->chains) === 1 ? $filters->chains[0]->value : null;
    }

    /**
     * Patří čistá adresa produktu (existující produkt), ne obchodu?
     */
    private function isProductSlug(OfferFilters $filters): bool
    {
        return $filters->productId !== null && $this->productSlug($filters->productId) !== null;
    }

    /**
     * Adresa akcí jednoho obchodu (`/akce/lidl`).
     */
    public function chainUrl(Chain $chain, bool $absolute = false): string
    {
        return $this->url(new OfferFilters([$chain]), absolute: $absolute);
    }

    /**
     * Prodejna podle části adresy `/akce/{obchod}/{prodejna}` (R172); null = obchod stránky
     * prodejen nemá, nebo taková prodejna není.
     *
     * @return array{chain: Chain, storeCode: string}|null
     */
    public function resolveStore(string $chainSlug, string $storeSlug): ?array
    {
        $chain = Chain::tryFrom($chainSlug);
        if ($chain === null) {
            return null;
        }

        foreach ($this->stores()[$chain->value] ?? [] as $code => $store) {
            if ($store['slug'] === $storeSlug) {
                return ['chain' => $chain, 'storeCode' => (string) $code];
            }
        }

        return null;
    }

    /**
     * Adresa stránky prodejny (`/akce/kaufland/trutnov`); null = prodejna stránku nemá.
     */
    public function storeUrl(string $storeCode, bool $absolute = false): ?string
    {
        $store = $this->store($storeCode);

        return $store === null ? null : $this->url(new OfferFilters([$store['chain']], storeCodes: [$storeCode], storeChoice: true), absolute: $absolute);
    }

    /**
     * Prodejna se stránkou podle kódu: obchod, název a město; null = stránku nemá.
     *
     * @return array{chain: Chain, name: string, city: string}|null
     */
    public function store(string $storeCode): ?array
    {
        foreach ($this->stores() as $chain => $stores) {
            if (isset($stores[$storeCode])) {
                return ['chain' => Chain::from($chain), 'name' => $stores[$storeCode]['name'], 'city' => $stores[$storeCode]['city']];
            }
        }

        return null;
    }

    /**
     * Prodejny obchodu se stránkou podle názvu — odkazy ze stránky obchodu a sitemap.xml (R172).
     *
     * @return list<array{code: string, name: string, city: string, url: string}>
     */
    public function storesOf(Chain $chain, bool $absolute = false): array
    {
        $list = [];
        foreach ($this->stores()[$chain->value] ?? [] as $code => $store) {
            $list[] = [
                'code' => (string) $code,
                'name' => $store['name'],
                'city' => $store['city'],
                'url' => route(self::STORE_ROUTE, ['slug' => $chain->value, 'store' => $store['slug']], $absolute),
            ];
        }

        return $list;
    }

    /**
     * Prodejna, jejíž stránka odpovídá filtrům: jeden obchod se stránkami prodejen, jedna jeho
     * prodejna, bez produktu a typu prodejny; jinak null.
     *
     * @return array{chain: Chain, slug: string}|null
     */
    private function storeOf(OfferFilters $filters): ?array
    {
        if (count($filters->chains) !== 1 || count($filters->storeCodes) !== 1 || $filters->productId !== null || $filters->storeFormat !== null) {
            return null;
        }
        $chain = $filters->chains[0];
        $store = $this->stores()[$chain->value][$filters->storeCodes[0]] ?? null;

        return $store === null ? null : ['chain' => $chain, 'slug' => $store['slug']];
    }

    /**
     * Prodejny obchodů se stránkami prodejen (R172) s částí adresy z názvu; shoda v rámci obchodu
     * (nebo rezervovaná cesta) dostane na konec kód prodejny. Pořadí podle názvu.
     *
     * @return array<string, array<string, array{slug: string, name: string, city: string}>>
     */
    private function stores(): array
    {
        if ($this->stores !== null) {
            return $this->stores;
        }

        $chains = array_values(array_map(fn (Chain $chain): string => $chain->value, array_filter(Chain::enabled(), $this->chains->hasStorePages(...))));
        $this->stores = [];
        $taken = [];
        $rows = $chains === [] ? [] : Store::query()->whereIn('chain', $chains)->orderBy('name')->orderBy('code')->get(['chain', 'code', 'name', 'city']);
        foreach ($rows as $row) {
            $chain = $row->chain->value;
            $slug = Str::slug($row->name);
            if ($slug === '' || isset($taken[$chain][$slug]) || in_array($slug, self::RESERVED_STORE_SLUGS, true)) {
                $slug = trim($slug.'-'.Str::slug($row->code), '-');
            }
            $taken[$chain][$slug] = true;
            $this->stores[$chain][$row->code] = ['slug' => $slug, 'name' => $row->name, 'city' => $row->city];
        }

        return $this->stores;
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
            ->joinCurrentOffers($this->calendar->today())
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
