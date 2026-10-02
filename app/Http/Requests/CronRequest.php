<?php

/**
 * Požadavek z cronu WebAdminu (R20) — ověří token z query stringu. Neplatný nebo nenastavený
 * token odpoví 404: URL navenek neexistuje, dokud se nezná token.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Sources\SourceRegistry;
use App\Enums\Chain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CronRequest extends FormRequest
{
    /**
     * Smí jen volání se správným tokenem; prázdný token v konfiguraci cron URL vypíná.
     */
    public function authorize(): bool
    {
        $expected = config('letaky.cron.token');
        $given = $this->query('token');

        return is_string($expected) && $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }

    /**
     * Obchod (u stahování akcí) musí mít zdroj nabídek.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $chains = array_map(fn (Chain $chain): string => $chain->value, app(SourceRegistry::class)->chainsWithOffers());

        return [
            'chain' => ['sometimes', 'required', 'string', Rule::in($chains)],
        ];
    }

    /**
     * Zvolený obchod.
     */
    public function chain(): Chain
    {
        return Chain::from($this->string('chain')->toString());
    }

    /**
     * Místo 403 odpoví 404, aby URL neprozrazovala, že existuje.
     */
    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException;
    }
}
