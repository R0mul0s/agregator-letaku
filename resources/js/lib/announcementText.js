/**
 * Odkazy v textu zprávy od nás (R175): `[Mých obchodech](/obchody)` → části textu a odkazů
 * pro vykreslení bez v-html. Adresa jen cesta v aplikaci nebo https (jiná zůstane textem —
 * server ji při odeslání odmítne). Vzor musí sedět s `App\Domain\Notifications\AnnouncementText`.
 *
 * @author Roman Hlaváček
 * @created 2026-10-25
 */

/** Odkaz s povolenou adresou: [slova](/cesta) nebo [slova](https://…); cesta bez „//“ a „/\“ (R113). */
const LINK_PATTERN = /\[([^\]\n]+)\]\((\/(?![/\\])[^\s\\)]*|https:\/\/[^\s)]+)\)/gu;

/** Povolená adresa odkazu (AnnouncementText::URL_PATTERN) — kontrola před vložením do textu. */
const URL_PATTERN = /^(\/(?!\/)[^\s\\]*|https:\/\/\S+)$/u;

/** Cesta v aplikaci (otevře se přechodem Inertie), ostatní adresy jsou cizí. */
const APP_PATH_PATTERN = /^\/(?![/\\])/;

/**
 * Rozdělí text na části [{ text }, { text, url, external }] v pořadí, jak jdou za sebou.
 *
 * @param {string} body
 * @returns {Array<{ text: string, url?: string, external?: boolean }>}
 */
export function announcementParts(body) {
    const parts = [];
    let last = 0;

    for (const match of body.matchAll(LINK_PATTERN)) {
        if (match.index > last) {
            parts.push({ text: body.slice(last, match.index) });
        }
        parts.push({ text: match[1], url: match[2], external: !APP_PATH_PATTERN.test(match[2]) });
        last = match.index + match[0].length;
    }
    if (last < body.length) {
        parts.push({ text: body.slice(last) });
    }

    return parts;
}

/**
 * Obsahuje text aspoň jeden odkaz? (Náhled ve formuláři jen tehdy.)
 *
 * @param {string} body
 * @returns {boolean}
 */
export function hasAnnouncementLink(body) {
    return announcementParts(body).some((part) => part.url);
}

/**
 * Je adresa povolená pro odkaz v textu (cesta v aplikaci nebo https)? Odkaz v textu navíc
 * nesmí obsahovat závorku — ukončila by značku.
 *
 * @param {string} url
 * @returns {boolean}
 */
export function isAnnouncementUrl(url) {
    return URL_PATTERN.test(url) && !url.includes(')');
}

/**
 * Značka odkazu pro vložení do textu.
 *
 * @param {string} text Slova odkazu
 * @param {string} url Adresa
 * @returns {string}
 */
export function announcementLink(text, url) {
    return `[${text}](${url})`;
}
