/**
 * Volba vzhledu: světlý / tmavý / podle systému.
 *
 * Volba se ukládá do localStorage a projeví se atributem data-theme na <html>
 * (styly: mixin dark v resources/scss/abstracts/_mixins.scss). Při načtení
 * stránky ji nastaví už public/theme-init.js — klíč a hodnoty musí sedět.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
import { ref } from 'vue';

const STORAGE_KEY = 'letaky-theme';
export const THEMES = ['light', 'dark', 'system'];
const SYSTEM = 'system';

/**
 * Přečte uloženou volbu; nedostupné úložiště nebo neznámá hodnota = podle systému.
 *
 * @returns {string}
 */
function readStored() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return THEMES.includes(stored) ? stored : SYSTEM;
    } catch {
        return SYSTEM;
    }
}

const preference = ref(readStored());

/**
 * Nastaví a uloží vzhled.
 *
 * @param {'light'|'dark'|'system'} theme
 */
function setTheme(theme) {
    preference.value = theme;

    if (theme === SYSTEM) {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', theme);
    }

    try {
        if (theme === SYSTEM) {
            window.localStorage.removeItem(STORAGE_KEY);
        } else {
            window.localStorage.setItem(STORAGE_KEY, theme);
        }
    } catch {
        // Nedostupné úložiště — volba platí jen do obnovení stránky.
    }
}

/**
 * Aktuální volba vzhledu a funkce pro její změnu.
 *
 * @returns {{ preference: import('vue').Ref<string>, setTheme: (theme: string) => void }}
 */
export function useTheme() {
    return { preference, setTheme };
}
