/**
 * Sdílení přes systémové okno (Web Share API — chat, e-mail, sociální sítě) na dotykovém
 * zařízení, jinak zkopírování do schránky (R146 — okno sdílení Windows text zkopírovat neumí). Nákupní seznam jako text i odkazem (R130) a akce z karty.
 * Povedené sdílení měří Google Analytics událostí `share` (R134, jen se souhlasem).
 *
 * @author Roman Hlaváček
 * @created 2026-10-10
 */
import { copyText } from '@/lib/clipboard';
import { trackEvent } from '@/lib/consent';

/** Co se sdílí — parametr `content_type` události share. */
export const SHARE_CONTENT = Object.freeze({
    OFFER: 'offer',
    SHOPPING_LIST: 'shopping_list',
    SHOPPING_LIST_LINK: 'shopping_list_link',
    WEEKLY: 'weekly',
});

/** Jak se sdílelo — parametr `method` události share. */
const SHARE_METHOD = Object.freeze({
    SYSTEM: 'system',
    CLIPBOARD: 'clipboard',
});

/** Ovládání prstem (telefon, tablet) — jen tam má systémové okno sdílení smysl. */
const COARSE_POINTER_QUERY = '(pointer: coarse)';

/**
 * Sdílet systémovým oknem? Jen na dotykovém zařízení, které ho umí. Na počítači (Chrome a Edge
 * ve Windows sdílení mají) okno nabízí jen aplikace a text zkopírovat nejde — tam schránka.
 *
 * @returns {boolean}
 */
export function canSystemShare() {
    return typeof navigator.share === 'function' && window.matchMedia(COARSE_POINTER_QUERY).matches;
}

/**
 * Změří povedené sdílení (R134).
 *
 * @param {string} contentType Hodnota ze SHARE_CONTENT
 * @param {boolean} copied Zkopírováno do schránky, ne sdíleno systémem
 */
export function trackShare(contentType, copied) {
    trackEvent('share', { method: copied ? SHARE_METHOD.CLIPBOARD : SHARE_METHOD.SYSTEM, content_type: contentType });
}

/**
 * Pošle obsah sdílením systému (dotykové zařízení); jinak zkopíruje text s adresou (každé na
 * svém řádku).
 * Zavření okna sdílení není chyba (a nezměří se).
 *
 * @param {{ title?: string, text?: string, url?: string }} content
 * @param {string} copiedMessage Toast po zkopírování
 * @param {string} failedMessage Toast, když schránka nejde
 * @param {string} contentType Co se sdílí (SHARE_CONTENT) — pro měření
 * @returns {Promise<void>}
 */
export async function shareOrCopy(content, copiedMessage, failedMessage, contentType) {
    if (canSystemShare()) {
        try {
            await navigator.share(content);
            trackShare(contentType, false);
        } catch {
            // Uživatel sdílení zavřel
        }

        return;
    }

    if (await copyText([content.text, content.url].filter(Boolean).join('\n'), copiedMessage, failedMessage)) {
        trackShare(contentType, true);
    }
}
