<?php

/**
 * Formulář hlídané položky — název a produkt z katalogu (R31), nebo vlastní slova (R18).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'keywords' => ['required_without:product_id', 'nullable', 'string', $keywordsMax],
            'variant_keywords' => ['nullable', 'string', $keywordsMax],
            'exclude_keywords' => ['nullable', 'string', $keywordsMax],
        ];
    }

    /**
     * Data k uložení: položka z katalogu nemá vlastní slova, položka se slovy nemá produkt.
     *
     * @return array{name: string, product_id: int|null, keywords: string|null, variant_keywords: string|null, exclude_keywords: string|null}
     */
    public function watchItemData(): array
    {
        $productId = $this->integer('product_id') ?: null;

        return [
            'name' => $this->string('name')->toString(),
            'product_id' => $productId,
            'keywords' => $productId === null ? $this->nullableString('keywords') : null,
            'variant_keywords' => $productId === null ? $this->nullableString('variant_keywords') : null,
            'exclude_keywords' => $productId === null ? $this->nullableString('exclude_keywords') : null,
        ];
    }

    /**
     * Textové pole; prázdné je null.
     */
    private function nullableString(string $key): ?string
    {
        $value = trim($this->string($key)->toString());

        return $value === '' ? null : $value;
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
     * Vlastní chybové hlášky.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keywords.required_without' => __('app.ui.watch.keywords_or_product'),
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
            'product_id' => __('app.ui.watch.product'),
            'keywords' => __('app.ui.watch.keywords'),
            'variant_keywords' => __('app.ui.watch.variant_keywords'),
            'exclude_keywords' => __('app.ui.watch.exclude_keywords'),
        ];
    }
}
