/**
 * Zkopírování textu do schránky s potvrzením v toastu (nákupní seznam R61, kontakt R91).
 * Schránka chce zabezpečené spojení a souhlas prohlížeče — když selže, toast to řekne.
 *
 * @author Roman Hlaváček
 * @created 2026-10-06
 */
import { showToast } from '@/lib/toast';

/**
 * Zkopíruje text a ukáže toast o výsledku.
 *
 * @param {string} text
 * @param {string} copiedMessage Toast po zkopírování
 * @param {string} failedMessage Toast, když schránka nejde
 * @returns {Promise<boolean>} Podařilo se?
 */
export async function copyText(text, copiedMessage, failedMessage) {
    try {
        await navigator.clipboard.writeText(text);
        showToast(copiedMessage);

        return true;
    } catch {
        showToast(failedMessage);

        return false;
    }
}
