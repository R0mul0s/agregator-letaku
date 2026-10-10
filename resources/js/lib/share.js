/**
 * Sdílení přes systémové okno (Web Share API — chat, e-mail, sociální sítě), bez něj
 * zkopírování do schránky. Nákupní seznam jako text i odkazem (R130) a akce z karty.
 *
 * @author Roman Hlaváček
 * @created 2026-10-10
 */
import { copyText } from '@/lib/clipboard';

/**
 * Pošle obsah sdílením systému; bez něj zkopíruje text s adresou (každé na svém řádku).
 * Zavření okna sdílení není chyba.
 *
 * @param {{ title?: string, text?: string, url?: string }} content
 * @param {string} copiedMessage Toast po zkopírování
 * @param {string} failedMessage Toast, když schránka nejde
 * @returns {Promise<void>}
 */
export async function shareOrCopy(content, copiedMessage, failedMessage) {
    if (navigator.share) {
        try {
            await navigator.share(content);
        } catch {
            // Uživatel sdílení zavřel
        }

        return;
    }

    await copyText([content.text, content.url].filter(Boolean).join('\n'), copiedMessage, failedMessage);
}
