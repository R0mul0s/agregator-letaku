<?php

/**
 * Souhlas s obchodními sděleními v účtu (R51): udělení nebo odvolání.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketingRequest extends FormRequest
{
    /** Sada chyb formuláře (každý formulář účtu má vlastní). */
    public const ERROR_BAG = 'marketing';

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
            'marketing' => ['required', 'boolean'],
        ];
    }

    /**
     * Souhlasí uživatel?
     */
    public function consent(): bool
    {
        return $this->boolean('marketing');
    }
}
