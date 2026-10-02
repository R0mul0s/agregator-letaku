<?php

/**
 * Parametry tabulky katalogu (R29, R43): hledání, oddělení, řazení a načtené stránky.
 * Filtruje a řadí server — katalog bude růst a v prohlížeči by se nevešel celý.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPageWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogIndexRequest extends FormRequest
{
    use HasPageWindow;

    /** Parametr adresy: oddělení (první úroveň stromu kategorií). */
    public const DEPARTMENT = 'oddeleni';

    /** Parametr adresy: sloupec řazení. */
    public const SORT = 'razeni';

    /** Parametr adresy: směr řazení. */
    public const DIRECTION = 'smer';

    /** Sloupce, podle kterých jde řadit. */
    public const SORTS = ['name', 'category', 'offers', 'watchers'];

    /** Výchozí řazení: podle názvu. */
    public const DEFAULT_SORT = 'name';

    /** Číselné sloupce se bez zvoleného směru řadí od největšího. */
    private const DESCENDING_BY_DEFAULT = ['offers', 'watchers'];

    private const ASCENDING = 'asc';

    private const DESCENDING = 'desc';

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.config()->integer('letaky.catalog.search_max_length')],
            self::DEPARTMENT => ['nullable', 'string'],
            self::SORT => ['nullable', Rule::in(self::SORTS)],
            self::DIRECTION => ['nullable', Rule::in([self::ASCENDING, self::DESCENDING])],
            ...$this->pageWindowRules(),
        ];
    }

    /**
     * Slova hledání (všechna musí být v názvu, slovech nebo kategorii produktu).
     *
     * @return list<string>
     */
    public function searchWords(): array
    {
        return preg_split('/\s+/u', trim($this->string('q')->toString()), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Zvolené oddělení, null = všechna.
     */
    public function department(): ?string
    {
        $department = trim($this->string(self::DEPARTMENT)->toString());

        return $department === '' ? null : $department;
    }

    /**
     * Sloupec řazení.
     */
    public function sort(): string
    {
        return $this->string(self::SORT)->toString() ?: self::DEFAULT_SORT;
    }

    /**
     * Řadí se sestupně? Bez zvoleného směru podle sloupce (čísla od největšího).
     */
    public function descending(): bool
    {
        return $this->filled(self::DIRECTION)
            ? $this->string(self::DIRECTION)->toString() === self::DESCENDING
            : in_array($this->sort(), self::DESCENDING_BY_DEFAULT, true);
    }

    /**
     * Parametry adresy, které odkazy stránkování zachovají (výchozí hodnoty vynechá).
     *
     * @return array<string, string>
     */
    public function listParameters(): array
    {
        $sort = $this->sort();

        return array_filter([
            'q' => implode(' ', $this->searchWords()),
            self::DEPARTMENT => $this->department() ?? '',
            self::SORT => $sort === self::DEFAULT_SORT ? '' : $sort,
            self::DIRECTION => $this->filled(self::DIRECTION) ? $this->string(self::DIRECTION)->toString() : '',
        ]);
    }
}
