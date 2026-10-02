<?php

/**
 * Předvolby Mých slev (R41): řazení akcí a minimální sleva.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OffersSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OffersPreferencesRequest extends FormRequest
{
    /** Sada chyb formuláře (každý formulář účtu má vlastní). */
    public const ERROR_BAG = 'offersPreferences';

    /** @var string */
    protected $errorBag = self::ERROR_BAG;

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'offers_sort' => ['required', Rule::enum(OffersSort::class)],
            'min_discount_percent' => ['nullable', 'integer', Rule::in(config()->array('letaky.account.min_discount_options'))],
        ];
    }

    /**
     * Data k uložení.
     *
     * @return array{offers_sort: OffersSort, min_discount_percent: int|null}
     */
    public function preferences(): array
    {
        return [
            'offers_sort' => $this->enum('offers_sort', OffersSort::class) ?? OffersSort::UnitPrice,
            'min_discount_percent' => $this->filled('min_discount_percent') ? $this->integer('min_discount_percent') : null,
        ];
    }
}
