/**
 * Volba vzhledu: světlý / tmavý / podle systému.
 *
 * Volba se ukládá do localStorage a projeví se atributem data-theme na <html>
 * (styly: mixin dark v resources/scss/abstracts/_mixins.scss). Při načtení
 * stránky ji nastaví už public/theme-init.js — klíč a hodnoty musí sedět. Barvu lišty prohlížeče
 * (`<meta name="theme-color">` pro světlý a tmavý režim systému) srovná s ručně zvoleným vzhledem
 * až tenhle modul (R170) — theme-init.js má v CSP otisk a jeho změna by chtěla nový (PASTI.md bod 54).
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

/** Metatagy barvy lišty prohlížeče s původní barvou a režimem systému, pro který platí. */
const themeColorMetas = Array.from(document.querySelectorAll('meta[name="theme-color"]')).map((meta) => ({
    meta,
    content: meta.getAttribute('content'),
    scheme: meta.getAttribute('media')?.includes('dark') ? 'dark' : 'light',
}));

/**
 * Barva lišty prohlížeče: s ručně zvoleným vzhledem jeho barva v obou metatazích (jinak by se
 * řídila režimem systému), podle systému původní dvojice (R170).
 *
 * @param {string} theme
 */
function applyThemeColor(theme) {
    const forced = themeColorMetas.find((item) => item.scheme === theme)?.content;
    themeColorMetas.forEach((item) => item.meta.setAttribute('content', theme === SYSTEM || !forced ? item.content : forced));
}

applyThemeColor(preference.value);

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
    applyThemeColor(theme);

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
