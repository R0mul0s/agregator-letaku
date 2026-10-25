<?php

/**
 * Odkazy v textu zprávy od nás (R175): admin označí slova a přidá k nim odkaz, uloží se jako
 * `[Mých obchodech](/obchody)`. Text zůstává čistý text bez HTML — stránka záznamu z něj odkazy
 * vykreslí (`lib/announcementText.js`, stejný vzor), výňatek v seznamu a upozornění v telefonu
 * dostanou jen slova odkazu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

final class AnnouncementText
{
    /**
     * Povolená adresa odkazu: cesta v aplikaci (/akce), nebo https adresa — jiné schéma
     * (javascript:, http:) ne; cesta bez zpětného lomítka — prohlížeč čte „/\evil.com“ jako
     * „//evil.com“ (R113). Pole Odkaz i odkazy v textu.
     */
    public const URL_PATTERN = '~^(/(?!/)[^\s\\\\]*|https://\S+)$~';

    /**
     * Odkaz v textu s povolenou adresou: [slova](adresa); adresa bez mezer a závorky.
     * Musí sedět s `LINK_PATTERN` v `resources/js/lib/announcementText.js`.
     */
    private const LINK_PATTERN = '~\[([^\]\n]+)\]\((/(?![/\\\\])[^\s\\\\)]*|https://[^\s)]+)\)~u';

    /** Cokoli, co vypadá jako odkaz v textu, i s nepovolenou adresou — ke kontrole. */
    private const LINK_LIKE_PATTERN = '~\[[^\]\n]+\]\(([^)\n]*)\)~u';

    /**
     * Text bez značek odkazů — jen slova odkazu (výňatek v seznamu, upozornění v telefonu).
     */
    public static function plain(string $body): string
    {
        return (string) preg_replace(self::LINK_PATTERN, '$1', $body);
    }

    /**
     * Má text odkaz s nepovolenou adresou (javascript:, http:, //host)?
     */
    public static function hasInvalidLink(string $body): bool
    {
        preg_match_all(self::LINK_LIKE_PATTERN, $body, $matches);

        foreach ($matches[1] as $url) {
            if (preg_match(self::URL_PATTERN, $url) !== 1) {
                return true;
            }
        }

        return false;
    }
}
