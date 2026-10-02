<?php

/**
 * Zdroj akční nabídky Kauflandu — stránka nabídky na prodejny.kaufland.cz (ZDROJE_DAT.md).
 *
 * Stahuje výchozí variantu nabídky bez volby prodejny (R15); příští týden, jen když ho
 * stránka aktuálního týdne ohlásí — jinak by parametr `next` vrátil zase aktuální týden.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Kaufland;

use App\Domain\Sources\OfferSource;
use App\Domain\Sources\SourceHttp;
use App\Enums\Chain;

final class KauflandOfferSource implements OfferSource
{
    private const WEEK_PARAMETER = 'kloffer-week';

    private const CURRENT_WEEK = 'current';

    private const NEXT_WEEK = 'next';

    public function __construct(
        private readonly SourceHttp $http,
        private readonly KauflandOfferParser $parser,
    ) {}

    /**
     * Kaufland.
     */
    public function chain(): Chain
    {
        return Chain::Kaufland;
    }

    /**
     * Nabídka aktuálního týdne, případně i příštího.
     */
    public function fetch(): array
    {
        $url = config()->string('letaky.sources.kaufland.offers_url');

        $current = $this->parser->parse($this->download($url, self::CURRENT_WEEK), $url);
        if (! $current->nextWeekPublished) {
            return [$current->batch];
        }

        return [$current->batch, $this->parser->parse($this->download($url, self::NEXT_WEEK), $url)->batch];
    }

    /**
     * HTML stránky nabídky zvoleného týdne.
     */
    private function download(string $url, string $week): string
    {
        return $this->http->request()->get($url, [self::WEEK_PARAMETER => $week])->body();
    }
}
