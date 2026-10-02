<?php

/**
 * Zdroje nabídek a prodejen jednotlivých obchodů podle konfigurace (config/letaky.php, sources).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use App\Domain\Sources\Exceptions\SourceNotImplemented;
use App\Enums\Chain;
use Illuminate\Contracts\Container\Container;

final class SourceRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * Obchody, které mají zdroj nabídek.
     *
     * @return list<Chain>
     */
    public function chainsWithOffers(): array
    {
        return $this->chainsWith('offers');
    }

    /**
     * Obchody, které mají zdroj seznamu prodejen.
     *
     * @return list<Chain>
     */
    public function chainsWithStores(): array
    {
        return $this->chainsWith('stores');
    }

    /**
     * Zdroj nabídek obchodu — vždy nová instance (vlastní odstup mezi požadavky).
     *
     * @throws SourceNotImplemented
     */
    public function offers(Chain $chain): OfferSource
    {
        $source = $this->container->make($this->className($chain, 'offers'));

        return $source instanceof OfferSource ? $source : throw SourceNotImplemented::for($chain, 'offers');
    }

    /**
     * Zdroj prodejen obchodu.
     *
     * @throws SourceNotImplemented
     */
    public function stores(Chain $chain): StoreSource
    {
        $source = $this->container->make($this->className($chain, 'stores'));

        return $source instanceof StoreSource ? $source : throw SourceNotImplemented::for($chain, 'stores');
    }

    /**
     * Obchody s nastavenou třídou zdroje daného druhu, v pořadí výčtu.
     *
     * @return list<Chain>
     */
    private function chainsWith(string $kind): array
    {
        return array_values(array_filter(
            Chain::cases(),
            fn (Chain $chain): bool => config("letaky.sources.{$chain->value}.{$kind}_source") !== null,
        ));
    }

    /**
     * Třída zdroje z konfigurace.
     *
     * @return class-string
     *
     * @throws SourceNotImplemented
     */
    private function className(Chain $chain, string $kind): string
    {
        $class = config("letaky.sources.{$chain->value}.{$kind}_source");

        return is_string($class) && class_exists($class) ? $class : throw SourceNotImplemented::for($chain, $kind);
    }
}
