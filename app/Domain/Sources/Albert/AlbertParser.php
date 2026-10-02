<?php

/**
 * Převod odpovědí Albertu na letáky s textem stránek pro zmínky bez ceny (R27, R36).
 *
 * Seznam letáků je GraphQL `getLeaflets` na albert.cz, stránky letáku jsou v `spreads.json`
 * prohlížeče Publitas: každá stránka má `text` (text stránky v pořadí čtení — z něj prohlížeč
 * skládá i atribut alt obrázku) a náhledy v několika velikostech. Ceny jsou v textu rozsekané
 * („31“ „90“, „3490“) a k produktu je přiřadit nejde, proto jen zmínky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Sources\Albert;

use App\Domain\Offers\Data\LeafletData;
use App\Domain\Offers\Data\LeafletPageData;
use App\Domain\Offers\LocalCalendar;
use App\Domain\Offers\Parsing\Text;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Enums\Chain;
use App\Enums\LeafletKind;
use App\Enums\StoreFormat;
use Carbon\CarbonImmutable;

final class AlbertParser
{
    /** Formát platnosti v GraphQL: UTC „29/09/2026 22:00:00“. */
    private const VALIDITY_FORMAT = 'd/m/Y H:i:s';

    public function __construct(private readonly LocalCalendar $calendar) {}

    /**
     * Hlavní (ne lokální) letáky z odpovědi `getLeaflets` pro jeden typ prodejny.
     *
     * @param  array<mixed>  $response
     * @return list<array{leaflet: LeafletData, viewUrl: string}>
     *
     * @throws SourceResponseChanged
     */
    public function leaflets(array $response, StoreFormat $format): array
    {
        $items = $response['data']['getLeaflets']['leaflets'] ?? null;
        if (! is_array($items)) {
            throw SourceResponseChanged::because(Chain::Albert, 'getLeaflets bez seznamu letáků');
        }

        $leaflets = [];
        foreach ($items as $item) {
            // Lokální varianty („…_frenstat“) platí jen v jednotlivých prodejnách
            if (! is_array($item) || ($item['isDefault'] ?? false) !== true) {
                continue;
            }

            $id = $item['id'] ?? null;
            $viewUrl = $item['viewUrl'] ?? null;
            $start = $item['validityStartDate'] ?? null;
            $end = $item['validityEndDate'] ?? null;
            if (! is_string($id) || ! is_string($viewUrl) || ! is_string($start) || ! is_string($end)) {
                throw SourceResponseChanged::because(Chain::Albert, 'leták bez ID, adresy nebo platnosti');
            }

            $leaflets[] = [
                'leaflet' => new LeafletData(
                    kind: LeafletKind::Leaflet,
                    externalId: $id,
                    title: Text::clean(is_string($item['title'] ?? null) ? $item['title'] : null),
                    format: $format,
                    validFrom: $this->calendar->startFromInstant($this->instant($start)),
                    validTo: $this->calendar->endFromInstant($this->instant($end)),
                    sourceUrl: $viewUrl,
                ),
                'viewUrl' => rtrim($viewUrl, '/').'/',
            ];
        }

        return $leaflets;
    }

    /**
     * Stránky letáku s textem ze `spreads.json`; stránka bez textu se vynechá.
     *
     * @param  array<mixed>  $spreads
     * @return list<LeafletPageData>
     *
     * @throws SourceResponseChanged
     */
    public function pages(array $spreads, string $viewUrl, string $imageBaseUrl, string $imageSize, string $pagePath): array
    {
        if ($spreads === []) {
            throw SourceResponseChanged::because(Chain::Albert, 'leták bez stránek');
        }

        $pages = [];
        foreach ($spreads as $spread) {
            foreach (is_array($spread) && is_array($spread['pages'] ?? null) ? $spread['pages'] : [] as $page) {
                $number = is_array($page) ? ($page['number'] ?? null) : null;
                $text = is_array($page) ? Text::clean(is_string($page['text'] ?? null) ? $page['text'] : null) : null;
                if (! is_int($number) || $text === null) {
                    continue;
                }

                $image = $page['images'][$imageSize] ?? null;
                $pages[$number] = new LeafletPageData(
                    number: $number,
                    text: $text,
                    imageUrl: is_string($image) ? $imageBaseUrl.$image : null,
                    pageUrl: $viewUrl.sprintf($pagePath, $number),
                );
            }
        }

        return array_values($pages);
    }

    /**
     * Okamžik v UTC z formátu GraphQL („29/09/2026 22:00:00“) jako ISO 8601.
     *
     * @throws SourceResponseChanged
     */
    private function instant(string $value): string
    {
        $instant = CarbonImmutable::createFromFormat(self::VALIDITY_FORMAT, $value, 'UTC');

        return $instant === null ? throw SourceResponseChanged::because(Chain::Albert, "neplatná platnost „{$value}“") : $instant->toIso8601ZuluString();
    }
}
