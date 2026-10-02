<?php

/**
 * Nastavení e-mailového souhrnu (R42): jak často ho posílat.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DigestFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DigestRequest extends FormRequest
{
    /** Sada chyb formuláře (každý formulář účtu má vlastní). */
    public const ERROR_BAG = 'digest';

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
            'digest_frequency' => ['required', Rule::enum(DigestFrequency::class)],
        ];
    }

    /**
     * Zvolená četnost (po validaci vždy platná).
     */
    public function frequency(): DigestFrequency
    {
        return $this->enum('digest_frequency', DigestFrequency::class) ?? DigestFrequency::Off;
    }
}
