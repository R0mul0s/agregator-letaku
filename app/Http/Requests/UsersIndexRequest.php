<?php

/**
 * Parametry přehledu uživatelů pro admina (R84): hledání podle jména nebo e-mailu, filtr
 * (online, aktivní za N dní, nastavení), řazení a načtené stránky (R43).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPageWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsersIndexRequest extends FormRequest
{
    use HasPageWindow;

    /** Parametr adresy: filtr. */
    public const FILTER = 'kdo';

    /** Parametr adresy: řazení. */
    public const SORT = 'razeni';

    /** Filtr: aktivní za posledních N dní (N z letaky.users.active_days) — „aktivni-7“. */
    public const ACTIVE_PREFIX = 'aktivni-';

    /** Filtry bez parametru: online, upozornění v telefonu, e-mailový souhrn, souhlas s novinkami, neověřený e-mail. */
    public const FIXED_FILTERS = ['online', 'telefon', 'souhrn', 'novinky', 'neovereni'];

    /** Řazení: naposledy online, nejnovější registrace, jméno, nejvíc hlídaných položek. */
    public const SORTS = ['last_seen', 'registered', 'name', 'watch_items'];

    /** Výchozí řazení: naposledy online nahoře. */
    public const DEFAULT_SORT = 'last_seen';

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.config()->integer('letaky.users.search_max_length')],
            self::FILTER => ['nullable', Rule::in(self::filters())],
            self::SORT => ['nullable', Rule::in(self::SORTS)],
            ...$this->pageWindowRules(),
        ];
    }

    /**
     * Všechny filtry: okna aktivity z konfigurace a pevné filtry.
     *
     * @return list<string>
     */
    public static function filters(): array
    {
        return [
            ...array_map(fn (int $days): string => self::ACTIVE_PREFIX.$days, self::activeDays()),
            ...self::FIXED_FILTERS,
        ];
    }

    /**
     * Okna aktivity ve dnech (letaky.users.active_days).
     *
     * @return list<int>
     */
    public static function activeDays(): array
    {
        /** @var list<int> $days */
        $days = config()->array('letaky.users.active_days');

        return $days;
    }

    /**
     * Slova hledání (všechna musí být ve jménu nebo e-mailu).
     *
     * @return list<string>
     */
    public function searchWords(): array
    {
        return preg_split('/\s+/u', trim($this->string('q')->toString()), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Zvolený filtr, null = všichni.
     */
    public function filter(): ?string
    {
        return $this->string(self::FILTER)->toString() ?: null;
    }

    /**
     * Zvolené řazení.
     */
    public function sort(): string
    {
        return $this->string(self::SORT)->toString() ?: self::DEFAULT_SORT;
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
            self::FILTER => $this->filter() ?? '',
            self::SORT => $sort === self::DEFAULT_SORT ? '' : $sort,
        ]);
    }
}
