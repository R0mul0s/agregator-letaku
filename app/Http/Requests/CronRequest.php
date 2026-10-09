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
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CronRequest extends FormRequest
{
    /** Úlohy jednoho obchodu — parametr `chain` je povinný. */
    private const CHAIN_ROUTES = ['cron.import-offers', 'cron.import-stores'];

    /** Stažení prodejen — jen obchod se zdrojem prodejen (R49). */
    private const STORES_ROUTE = 'cron.import-stores';

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
     * Obchod: u stahování akcí povinný se zdrojem nabídek, u prodejen se zdrojem prodejen.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sources = app(SourceRegistry::class);
        $chains = $this->routeIs(self::STORES_ROUTE) ? $sources->chainsWithStores() : $sources->chainsWithOffers();
        $required = $this->routeIs(self::CHAIN_ROUTES) ? 'required' : 'sometimes';

        return [
            'chain' => [$required, 'string', Rule::in(array_map(fn (Chain $chain): string => $chain->value, $chains))],
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

    /**
     * Chybný parametr = 422 jako prostý text (R113). Výchozí přesměrování zpět by cron
     * WebAdminu viděl jako úspěch (302 → úvodní stránka 200).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response(
            implode("\n", $validator->errors()->all())."\n",
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'],
        ));
    }
}
