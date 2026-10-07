<?php

/**
 * Odkazy stránkování pro komponentu Pagination.vue (R43): čísla stránek (první, poslední
 * a okolí načteného rozsahu, mezery „…“), předchozí a další stránka, „Načíst další“
 * (rozsah prodloužený o stránku) a „Zobrazeno 1–100 z 6 245“. Adresy staví volající —
 * zná parametry svého výpisu (hledání, filtry, řazení).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support\Pagination;

use Closure;

final class PaginationLinks
{
    /** Hlavička „Načíst další“, které připojí jen novou stránku (R106, HasPageWindow::appendsPage). */
    public const LOAD_MORE_HEADER = 'X-Load-More';

    /**
     * Jak má „Načíst další“ načíst jen novou stránku a připojit ji (R106): které props stránky
     * znovu načíst a s jakou hlavičkou. Jen pro výpis, jehož kontroler připojení umí.
     *
     * @param  list<string>  $only  Props výpisu a stránkování
     * @return array{only: list<string>, headers: array<string, string>}
     */
    public static function append(array $only): array
    {
        return ['only' => $only, 'headers' => [self::LOAD_MORE_HEADER => '1']];
    }

    /**
     * Data stránkování pro stránku.
     *
     * @param  Closure(int $page, int|null $from): string  $url  Adresa stránky; s `from` rozsah od této stránky
     * @return array<string, mixed>
     */
    public static function for(PageWindow $window, int $total, Closure $url): array
    {
        $lastPage = $window->lastPage($total);
        $neighbours = config()->integer('letaky.pagination.page_link_neighbours');
        $numbers = array_unique([
            1,
            ...range(max(1, $window->from - $neighbours), min($lastPage, $window->to + $neighbours)),
            $lastPage,
        ]);
        sort($numbers);

        $pages = [];
        $previous = null;
        foreach ($numbers as $number) {
            if ($previous !== null && $number > $previous + 1) {
                $pages[] = ['gap' => true];
            }
            $pages[] = ['number' => $number, 'url' => $url($number, null), 'current' => $window->contains($number)];
            $previous = $number;
        }

        $hasNext = $window->to < $lastPage;

        return [
            'from' => $window->from,
            'to' => $window->to,
            'lastPage' => $lastPage,
            'pages' => $pages,
            'previousUrl' => $window->from > 1 ? $url($window->from - 1, null) : null,
            'nextUrl' => $hasNext ? $url($window->to + 1, null) : null,
            'loadMoreUrl' => $hasNext ? $url($window->to + 1, $window->from) : null,
            'loadMoreCount' => $hasNext ? min($window->perPage, $total - $window->to * $window->perPage) : 0,
            'shownFrom' => $total === 0 ? 0 : $window->offset() + 1,
            'shownTo' => min($total, $window->to * $window->perPage),
        ];
    }

    /**
     * Parametry stránky do adresy: první stránka a rozsah jedné stránky parametry nemají.
     *
     * @return array<string, int>
     */
    public static function parameters(int $page, ?int $from, string $pageParameter, string $fromParameter): array
    {
        return array_filter([
            $fromParameter => $from !== null && $from < $page ? $from : null,
            $pageParameter => $page > 1 ? $page : null,
        ]);
    }
}
