<?php

/**
 * Parametry přehledu nabídek — hledaný text, obchod, produkt katalogu (R71), jen budoucí
 * akce (R76) a načtené stránky (R43).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Chain;
use App\Http\Requests\Concerns\HasPageWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OffersRequest extends FormRequest
{
    use HasPageWindow;

    /** Parametr adresy: akce jednoho produktu katalogu (z našeptávače, R71). */
    public const PRODUCT = 'produkt';

    /** Parametr adresy: jen akce, které ještě nezačaly (R76). */
    public const UPCOMING = 'brzy';

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
            self::PRODUCT => ['nullable', 'integer', Rule::exists('products', 'id')],
            self::UPCOMING => ['nullable', 'boolean'],
            ...$this->pageWindowRules(),
        ];
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

    /**
     * ID produktu katalogu, jehož akce ukázat, nebo null.
     */
    public function productId(): ?int
    {
        return $this->integer(self::PRODUCT) ?: null;
    }

    /**
     * Jen akce, které ještě nezačaly?
     */
    public function upcomingOnly(): bool
    {
        return $this->boolean(self::UPCOMING);
    }
}
