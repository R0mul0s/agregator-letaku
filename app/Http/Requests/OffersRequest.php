<?php

/**
 * Parametry přehledu nabídek — hledaný text, obchod a načtené stránky (R43).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Chain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OffersRequest extends FormRequest
{
    /** Parametr adresy: poslední načtená stránka (R43). */
    public const PAGE = 'strana';

    /** Parametr adresy: první načtená stránka po „Načíst další“ (R43). */
    public const FROM_PAGE = 'od';

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.config()->integer('letaky.offers.search_max_length')],
            'chain' => ['nullable', Rule::enum(Chain::class)],
            self::PAGE => ['nullable', 'integer', 'min:1'],
            self::FROM_PAGE => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Načtená stránka (poslední z rozsahu); bez parametru první.
     */
    public function page(): ?int
    {
        return $this->filled(self::PAGE) ? $this->integer(self::PAGE) : null;
    }

    /**
     * První stránka rozsahu po „Načíst další“; bez parametru stejná jako page().
     */
    public function fromPage(): ?int
    {
        return $this->filled(self::FROM_PAGE) ? $this->integer(self::FROM_PAGE) : null;
    }

    /**
     * Hledaný text; prázdný je null.
     */
    public function searchText(): ?string
    {
        $text = trim($this->string('q')->toString());

        return $text === '' ? null : $text;
    }

    /**
     * Zvolený obchod.
     */
    public function chain(): ?Chain
    {
        return $this->enum('chain', Chain::class);
    }
}
