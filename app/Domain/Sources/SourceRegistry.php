<?php

/**
 * Zdroje nabídek jednotlivých obchodů podle konfigurace (config/letaky.php, sources).
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
     * Obchody, které mají zdroj nabídek, v pořadí výčtu.
     *
     * @return list<Chain>
     */
    public function chainsWithOffers(): array
    {
        return array_values(array_filter(
            Chain::cases(),
            fn (Chain $chain): bool => config("letaky.sources.{$chain->value}.offers_source") !== null,
        ));
    }

    /**
     * Zdroj nabídek obchodu — vždy nová instance (vlastní odstup mezi požadavky).
     *
     * @throws SourceNotImplemented
     */
    public function offers(Chain $chain): OfferSource
    {
        $class = config("letaky.sources.{$chain->value}.offers_source");
        $source = is_string($class) && class_exists($class) ? $this->container->make($class) : null;

        return $source instanceof OfferSource ? $source : throw SourceNotImplemented::for($chain);
    }
}
