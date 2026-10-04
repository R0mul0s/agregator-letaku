<?php

/**
 * Adresa odběru upozornění v telefonu (R66) musí patřit push službě prohlížeče
 * (`letaky.push.allowed_hosts`, i subdomény) a vést přes HTTPS. Server na ni posílá
 * požadavky — bez kontroly by ho šlo poslat na libovolnou adresu, i do vnitřní sítě (SSRF).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PushServiceEndpoint implements ValidationRule
{
    /** Push služby přijímají jen šifrované spojení. */
    private const SCHEME = 'https';

    /**
     * Ověří schéma a doménu adresy.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isAllowed($value)) {
            $fail(__('app.ui.push.invalid_endpoint'));
        }
    }

    /**
     * HTTPS a doména ze seznamu nebo její subdoména (wns2-par02p.notify.windows.com).
     */
    private function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== self::SCHEME || isset($parts['port']) || isset($parts['user'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        foreach (config()->array('letaky.push.allowed_hosts') as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
