<?php

/**
 * Jak často posílat e-mailový souhrn nových akcí hlídaných položek (R42).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Enums;

enum DigestFrequency: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * Kolik hodin musí od posledního souhrnu uplynout, než přijde další (letaky.digest);
     * null = souhrn se neposílá.
     */
    public function intervalHours(): ?int
    {
        return $this === self::Off ? null : config()->integer('letaky.digest.interval_hours.'.$this->value);
    }

    /**
     * Název pro zobrazení (lang/cs/app.php, skupina ui.digest_frequency).
     */
    public function label(): string
    {
        return __('app.ui.digest_frequency.'.$this->value);
    }
}
