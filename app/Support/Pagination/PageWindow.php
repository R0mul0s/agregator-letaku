<?php

/**
 * Rozsah stránek výpisu (R43, Všechny akce i katalog): od stránky `from` po stránku `to` včetně. „Načíst další“
 * rozsah prodlouží, adresa ho nese (?od=1&strana=3), takže po obnovení nebo návratu
 * zpět zůstane načteno totéž. Rozsah má strop, aby adresa nevynutila tisíce akcí najednou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support\Pagination;

final readonly class PageWindow
{
    private function __construct(
        public int $from,
        public int $to,
        public int $perPage,
    ) {}

    /**
     * Rozsah z parametrů adresy; chybějící „od“ = jen stránka `to`. Nejvýš `maxPages` stránek —
     * delší rozsah se zkrátí zepředu (poslední načtená stránka zůstane).
     */
    public static function of(?int $from, ?int $to, int $perPage, int $maxPages): self
    {
        $to = max(1, $to ?? 1);
        $from = min(max(1, $from ?? $to), $to);

        return new self(max($from, $to - $maxPages + 1), $to, $perPage);
    }

    /**
     * Rozsah omezený na existující stránky (hledání mohlo mezitím vrátit méně akcí).
     */
    public function within(int $lastPage): self
    {
        $to = min($this->to, max(1, $lastPage));

        return new self(min($this->from, $to), $to, $this->perPage);
    }

    /**
     * Počet stránek pro daný počet položek (aspoň jedna, i prázdná).
     */
    public function lastPage(int $total): int
    {
        return max(1, (int) ceil($total / $this->perPage));
    }

    /**
     * Kolik položek přeskočit.
     */
    public function offset(): int
    {
        return ($this->from - 1) * $this->perPage;
    }

    /**
     * Kolik položek načíst (všechny stránky rozsahu).
     */
    public function limit(): int
    {
        return ($this->to - $this->from + 1) * $this->perPage;
    }

    /**
     * Jen poslední stránka rozsahu — „Načíst další“ ji připojí pod už načtené (R106).
     */
    public function lastOnly(): self
    {
        return new self($this->to, $this->to, $this->perPage);
    }

    /**
     * Patří stránka do načteného rozsahu?
     */
    public function contains(int $page): bool
    {
        return $page >= $this->from && $page <= $this->to;
    }
}
