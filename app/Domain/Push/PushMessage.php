<?php

/**
 * Obsah upozornění v telefonu (R66) — co service worker (resources/pwa/service-worker.js)
 * ukáže: nadpis, text, kam klepnutí vede a číslo na ikoně aplikace.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Domain\Push;

final readonly class PushMessage
{
    /**
     * @param  string  $url  Adresa v aplikaci (bez domény), kterou klepnutí otevře
     * @param  string  $tag  Upozornění se stejnou značkou nahradí předchozí — v liště jich nepřibývá
     * @param  int|null  $badge  Číslo na ikoně aplikace (nové akce); null = beze změny
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $tag,
        public ?int $badge = null,
    ) {}

    /**
     * JSON pro service worker (událost push).
     */
    public function toJson(): string
    {
        return json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->tag,
            'badge' => $this->badge,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
