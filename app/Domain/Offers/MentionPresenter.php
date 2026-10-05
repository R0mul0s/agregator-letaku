<?php

/**
 * Zmínka v letáku bez ceny (R27) připravená pro stránku — obchod, leták, platnost a odkaz na stránku.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Offers;

use App\Enums\MatchStatus;
use App\Models\LeafletPage;
use Carbon\CarbonImmutable;

final class MentionPresenter
{
    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(private readonly LocalCalendar $calendar) {}

    /**
     * Data jedné zmínky pro Vue.
     *
     * @return array<string, mixed>
     */
    public function toPage(LeafletPage $page, MatchStatus $status): array
    {
        $leaflet = $page->leaflet;

        return [
            'id' => $page->id,
            'chain' => $leaflet->chain->value,
            'chainName' => $leaflet->chain->label(),
            'leafletTitle' => $leaflet->title,
            // Obchod s odlišnými letáky pro hypermarkety a supermarkety (Albert, Tesco)
            'storeFormatName' => $leaflet->format?->label(),
            'pageNumber' => $page->number,
            'validFrom' => $leaflet->valid_from?->format(self::DATE_FORMAT),
            'validTo' => $leaflet->valid_to?->format(self::DATE_FORMAT),
            // Leták, který ještě nezačal (R76): za kolik dní začne; null = už platí nebo začátek neznáme
            'startsInDays' => $this->startsInDays($leaflet->valid_from),
            'matchStatus' => $status->value,
            // Náhled stránky z CDN obchodu, nestahuje se (R22)
            'imageUrl' => $page->image_url,
            'pageUrl' => $page->page_url ?? $leaflet->source_url,
        ];
    }

    /**
     * Za kolik dní leták začne (R76); null, když už platí nebo začátek neznáme.
     */
    private function startsInDays(?CarbonImmutable $validFrom): ?int
    {
        $today = $this->calendar->today();

        return $validFrom !== null && $validFrom->greaterThan($today) ? (int) $today->diffInDays($validFrom) : null;
    }
}
