<?php

/**
 * Klíče VAPID pro upozornění v telefonu (web push, R66) — jimi se server prokazuje push
 * službám prohlížečů. Bez klíčů v .env je funkce vypnutá: přepínač v účtu se neukáže a cron
 * nic neposílá. Změna klíčů zneplatní všechny odběry (prohlížeč je vázaný na veřejný klíč).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push;

final class Vapid
{
    /**
     * Jsou nastavené oba klíče?
     */
    public function isConfigured(): bool
    {
        return $this->publicKey() !== null && $this->privateKey() !== null;
    }

    /**
     * Veřejný klíč (base64url) — dostane ho prohlížeč při přihlášení k odběru.
     */
    public function publicKey(): ?string
    {
        return $this->key('public_key');
    }

    /**
     * Soukromý klíč (base64url) — jen na serveru, podepisuje požadavky na push služby.
     */
    public function privateKey(): ?string
    {
        return $this->key('private_key');
    }

    /**
     * Kontakt na provozovatele pro push služby (při problémech se ozvou sem).
     */
    public function subject(): string
    {
        return 'mailto:'.config()->string('letaky.operator.email');
    }

    /**
     * Klíč z konfigurace; prázdný řetězec z .env = nenastavený.
     */
    private function key(string $name): ?string
    {
        $value = config('letaky.push.vapid.'.$name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
