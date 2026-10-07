<?php

/**
 * Parametry stránkování výpisu s „Načíst další“ (R43): ?strana=3 je poslední načtená stránka,
 * ?od=1 první — stejné ve Všech akcích i v katalogu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Pagination\PageWindow;
use App\Support\Pagination\PaginationLinks;
use Inertia\Support\Header;

trait HasPageWindow
{
    /** Parametr adresy: poslední načtená stránka. */
    public const PAGE = 'strana';

    /** Parametr adresy: první načtená stránka po „Načíst další“. */
    public const FROM_PAGE = 'od';

    /**
     * Pravidla validace stránkování.
     *
     * @return array<string, list<string>>
     */
    protected function pageWindowRules(): array
    {
        return [
            self::PAGE => ['nullable', 'integer', 'min:1'],
            self::FROM_PAGE => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Načtený rozsah stránek (strop letaky.pagination.max_loaded_pages).
     */
    public function pageWindow(int $perPage): PageWindow
    {
        return PageWindow::of(
            $this->filled(self::FROM_PAGE) ? $this->integer(self::FROM_PAGE) : null,
            $this->filled(self::PAGE) ? $this->integer(self::PAGE) : null,
            $perPage,
            config()->integer('letaky.pagination.max_loaded_pages'),
        );
    }

    /**
     * Je to „Načíst další“, které jen připojí poslední stránku rozsahu pod už načtené (R106)?
     * Částečné načtení Inertie s hlavičkou z PaginationLinks::append a rozsah, který strop
     * nezkrátil zepředu — jinak by připojené akce neseděly s tím, co stránka ukazuje, a server
     * pošle celý rozsah jako dřív.
     */
    public function appendsPage(PageWindow $window, string $prop): bool
    {
        $partial = explode(',', (string) $this->header(Header::PARTIAL_ONLY));

        return $this->hasHeader(PaginationLinks::LOAD_MORE_HEADER)
            && in_array($prop, $partial, true)
            && $window->from < $window->to
            && $this->integer(self::FROM_PAGE) === $window->from;
    }
}
