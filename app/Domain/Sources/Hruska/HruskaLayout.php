<?php

/**
 * Výšky písma (body PDF) jednoho rozvržení letáku Hrušky (R183). Parser podle nich pozná ceny,
 * název, popis a cenu za jednotku dlaždice. Leták 46/26 (11. 11. 2026, soubor „-web.pdf“) přešel
 * na jiné velikosti písma a klubovou cenu stejně velkou jako běžnou cenovku — parser rozvržení
 * pozná podle výšky cen za jednotku (`HruskaLeafletParser::layout()`).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-12
 */

declare(strict_types=1);

namespace App\Domain\Sources\Hruska;

final readonly class HruskaLayout
{
    /**
     * @param  float  $priceMinHeight  Nejmenší koruny ceny (i běžné ceny pod klubovou)
     * @param  float  $anchorMinHeight  Cenovka, ke které se hledá dlaždice („NAŠE CENA“, klubová cena)
     * @param  float|null  $clubMinHeight  Klubová cena má větší koruny než běžná cenovka; null = stejně velké
     *                                     (klubovou pozná běžná cena pod ní)
     * @param  float  $regularMaxHeight  Běžná cena pod klubovou je menší
     * @param  float  $nameMinHeight  Řádky názvu
     * @param  float|null  $brandMaxHeight  Značka nad názvem má menší písmo; null = stejné jako název
     * @param  float  $detailMinHeight  Popis s balením
     * @param  float  $unitMinHeight  Cena za jednotku
     */
    public function __construct(
        public float $priceMinHeight,
        public float $anchorMinHeight,
        public ?float $clubMinHeight,
        public float $regularMaxHeight,
        public float $nameMinHeight,
        public float $nameMaxHeight,
        public ?float $brandMaxHeight,
        public float $detailMinHeight,
        public float $detailMaxHeight,
        public float $unitMinHeight,
        public float $unitMaxHeight,
    ) {}

    /**
     * Letáky 43/26–45/26 (R165): cenovka 34,7 / 20,2, klubová cena 37,1 a pod ní běžná 23,5, značka
     * 10,9, název 11,1, popis 8,5, cena za jednotku 7,3 (štítky „NAŠE CENA“ a „BEZ LEPKU“ 7,4).
     */
    public static function original(): self
    {
        return new self(
            priceMinHeight: 20.0,
            anchorMinHeight: 30.0,
            clubMinHeight: 36.0,
            regularMaxHeight: 30.0,
            nameMinHeight: 10.7,
            nameMaxHeight: 11.3,
            brandMaxHeight: 11.0,
            detailMinHeight: 8.3,
            detailMaxHeight: 8.7,
            unitMinHeight: 7.2,
            unitMaxHeight: 7.35,
        );
    }

    /**
     * Od letáku 46/26 (R183): cenovka i klubová cena 37,7 / 22,0 (klubová se štítkem „S KLUBEM“),
     * běžná cena pod klubovou 23,4 / 13,6, značka i název 10,4, popis 9,1 (štítek „NAŠE CENA“ taky
     * 9,1, ale nad cenou), nálepky „od 300 g“ 9,3, cena za jednotku 7,8 (štítky „BEZ“, „S KLUBEM“ taky).
     */
    public static function web(): self
    {
        return new self(
            priceMinHeight: 20.0,
            anchorMinHeight: 30.0,
            clubMinHeight: null,
            regularMaxHeight: 30.0,
            nameMinHeight: 10.2,
            nameMaxHeight: 10.6,
            brandMaxHeight: null,
            detailMinHeight: 9.0,
            detailMaxHeight: 9.2,
            unitMinHeight: 7.7,
            unitMaxHeight: 7.9,
        );
    }

    /**
     * Rozvržení, která parser zkouší, v pořadí.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return [self::original(), self::web()];
    }
}
