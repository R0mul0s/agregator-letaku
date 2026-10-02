<?php

/**
 * Formulář produktu katalogu — název, kategorie a pravidla jako u hlídané položky (R29).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Pravidla validace; název je v katalogu jedinečný.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $keywordsMax = 'max:'.config()->integer('letaky.watch.keywords_max_length');
        $product = $this->route('product');

        return [
            'name' => [
                'required', 'string', 'max:'.config()->integer('letaky.watch.name_max_length'),
                Rule::unique('products', 'name')->ignore($product instanceof Product ? $product->id : null),
            ],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'keywords' => ['required', 'string', $keywordsMax],
            'variant_keywords' => ['nullable', 'string', $keywordsMax],
            'exclude_keywords' => ['nullable', 'string', $keywordsMax],
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
            'name' => __('app.ui.catalog.name'),
            'category_id' => __('app.ui.catalog.category'),
            'keywords' => __('app.ui.watch.keywords'),
            'variant_keywords' => __('app.ui.watch.variant_keywords'),
            'exclude_keywords' => __('app.ui.watch.exclude_keywords'),
        ];
    }
}
