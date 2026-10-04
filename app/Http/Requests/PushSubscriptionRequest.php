<?php

/**
 * Zapnutí upozornění v telefonu (R66): odběr z prohlížeče (PushSubscription.toJSON()) —
 * adresa push služby a klíče k šifrování zpráv.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PushServiceEndpoint;
use Illuminate\Foundation\Http\FormRequest;

class PushSubscriptionRequest extends FormRequest
{
    /** Klíče prohlížeče v base64url (případně s doplněním „=“). */
    private const BASE64URL_PATTERN = '/^[A-Za-z0-9_-]+={0,2}$/';

    /**
     * Pravidla validace — délky podle klíčů prohlížeče: p256dh je bod křivky P-256
     * (65 bajtů = 87 znaků), auth 16 bajtů (22 znaků); rezerva na doplnění.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:500', 'url', new PushServiceEndpoint],
            'keys.p256dh' => ['required', 'string', 'between:80,100', 'regex:'.self::BASE64URL_PATTERN],
            'keys.auth' => ['required', 'string', 'between:16,50', 'regex:'.self::BASE64URL_PATTERN],
        ];
    }

    /**
     * Adresa push služby.
     */
    public function endpoint(): string
    {
        return $this->string('endpoint')->toString();
    }

    /**
     * Veřejný klíč prohlížeče (p256dh).
     */
    public function publicKey(): string
    {
        return $this->string('keys.p256dh')->toString();
    }

    /**
     * Tajemství prohlížeče (auth).
     */
    public function authToken(): string
    {
        return $this->string('keys.auth')->toString();
    }
}
