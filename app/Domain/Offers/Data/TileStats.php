<?php

/**
 * Kolik cen parser letáku z PDF nebo SVG našel a kolik z nich ověřil jako akci (R129) —
 * podklad přehledu kvality dat. Neověřená cena zůstane nejvýš zmínkou bez ceny (R27);
 * propad podílu ověřených znamená změněné rozvržení letáku nebo rozbitý parser.
 *
 * Počítá se po stranách před sloučením stejných akcí z více stran — podíl je tak srovnatelný
 * mezi letáky různé velikosti.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers\Data;

final readonly class TileStats
{
    /**
     * @param  int  $candidates  Ceny, ke kterým parser hledal dlaždici (velké ceny letáku)
     * @param  int  $verified  Ceny přijaté jako akce (ověřené cenou za jednotku, rozvržením nebo štítkem)
     */
    public function __construct(
        public int $candidates = 0,
        public int $verified = 0,
    ) {}

    /**
     * Součet se statistikou další strany nebo letáku.
     */
    public function plus(self $other): self
    {
        return new self($this->candidates + $other->candidates, $this->verified + $other->verified);
    }
}
