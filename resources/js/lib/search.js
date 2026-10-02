/**
 * Hledání v textu na straně prohlížeče — bez diakritiky a velkých písmen.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */

/**
 * Text pro porovnání: malá písmena bez diakritiky („Máslo“ → „maslo“).
 *
 * @param {string} text
 * @returns {string}
 */
export function normalizeSearch(text) {
    return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}
