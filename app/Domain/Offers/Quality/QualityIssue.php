<?php

/**
 * Propad v posledním stažení letáku (R129): málo akcí, nebo nízký podíl ověřených cen proti
 * obvyklému stavu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Offers\Quality;

final readonly class QualityIssue
{
    /** Stažení uložilo výrazně méně akcí než předchozí stažení téhož letáku. */
    public const OFFERS = 'offers';

    /** Parser ověřil výrazně menší podíl cen než obvykle. */
    public const VERIFIED = 'verified';

    /**
     * @param  self::OFFERS|self::VERIFIED  $kind
     * @param  int  $current  Počet akcí, nebo podíl ověřených cen v procentech
     * @param  int  $baseline  Obvyklá hodnota, se kterou se porovnává
     */
    public function __construct(
        public string $kind,
        public int $current,
        public int $baseline,
    ) {}
}
