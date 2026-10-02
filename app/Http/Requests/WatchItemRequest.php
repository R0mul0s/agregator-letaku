<?php

/**
 * Formulář hlídané položky — název a slova (R18).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WatchItemRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $keywordsMax = 'max:'.config()->integer('letaky.watch.keywords_max_length');

        return [
            'name' => ['required', 'string', 'max:'.config()->integer('letaky.watch.name_max_length')],
            'keywords' => ['required', 'string', $keywordsMax],
            'variant_keywords' => ['nullable', 'string', $keywordsMax],
            'exclude_keywords' => ['nullable', 'string', $keywordsMax],
        ];
    }

    /**
     * Nová položka nad limit počtu položek uživatele je chyba.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $limit = config()->integer('letaky.watch.max_items_per_user');
                if ($this->isMethod('post') && $this->user()?->watchItems()->count() >= $limit) {
                    $validator->errors()->add('name', __('app.ui.watch.limit', ['count' => $limit]));
                }
            },
        ];
    }

    /**
     * Názvy polí v chybových hláškách.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.ui.watch.name'),
            'keywords' => __('app.ui.watch.keywords'),
            'variant_keywords' => __('app.ui.watch.variant_keywords'),
            'exclude_keywords' => __('app.ui.watch.exclude_keywords'),
        ];
    }
}
