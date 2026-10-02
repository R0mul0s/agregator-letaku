<?php

/**
 * Nahrání profilového obrázku (R40). Prohlížeč ho předem ořízne na čtverec
 * a zmenší (letaky.account.avatar.size_px); server ověří typ, velikost a rozměry.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class AvatarRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $size = config()->integer('letaky.account.avatar.size_px');

        return [
            'avatar' => [
                'required',
                'file',
                'mimes:'.implode(',', config()->array('letaky.account.avatar.mimes')),
                'max:'.config()->integer('letaky.account.avatar.max_kilobytes'),
                'dimensions:max_width='.$size.',max_height='.$size,
            ],
        ];
    }

    /**
     * Nahraný soubor (po validaci vždy existuje).
     */
    public function avatar(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('avatar');

        return $file;
    }

    /**
     * Názvy polí v chybových hláškách.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['avatar' => __('app.ui.account.avatar')];
    }
}
