<?php

/**
 * „Tohle ne“ (R125) — slovo z názvu akce, které uživatel přidá mezi vyloučená slova hlídané
 * položky. Jedno slovo jen z písmen, dost dlouhé a takové, které neskryje všechny akce položky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Matching\ExclusionSuggestions;
use App\Domain\Matching\TextNormalizer;
use App\Domain\Matching\WatchRule;
use App\Models\WatchItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class ExcludeWordRequest extends FormRequest
{
    /**
     * Slovo smí přidat jen vlastník položky.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->watchItem());
    }

    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Jen písmena jako návrhy (ExclusionSuggestions) — slovo je i v adrese pro vrácení
            'word' => ['required', 'string', 'max:'.config()->integer('letaky.watch.exclusion_word_max_length'), 'regex:/^\p{L}+$/u'],
        ];
    }

    /**
     * Slovo, které by skrylo všechny akce položky, je krátké, nebo by se do vyloučených slov
     * nevešlo, je chyba.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $item = $this->watchItem()->loadMissing('product');
                $rule = WatchRule::fromWatchItem($item, app(TextNormalizer::class));
                if (! app(ExclusionSuggestions::class)->isAllowed($rule, $this->word())) {
                    $validator->errors()->add('word', __('app.ui.offer_actions.word_not_allowed'));
                } elseif (mb_strlen(trim($item->exclude_keywords.' '.$this->word())) > config()->integer('letaky.watch.keywords_max_length')) {
                    $validator->errors()->add('word', __('app.ui.offer_actions.words_full'));
                }
            },
        ];
    }

    /**
     * Slovo k vyloučení, malými písmeny.
     */
    public function word(): string
    {
        return mb_strtolower(trim($this->string('word')->toString()));
    }

    /**
     * Hlídaná položka z adresy.
     */
    private function watchItem(): WatchItem
    {
        $item = $this->route('watchItem');
        assert($item instanceof WatchItem);

        return $item;
    }
}
