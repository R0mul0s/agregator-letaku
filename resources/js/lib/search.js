/**
 * Hledání v textu na straně prohlížeče — bez diakritiky a velkých písmen, shoda od začátku
 * slova, zvýraznění shody, podobnost slov (překlepy) a poslední hledání (R71).
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
import { readStored, writeStored } from '@/lib/storage';

/** Klíč v localStorage s posledními hledáními ve Všech akcích (zásady, kap. 5). */
const RECENT_KEY = 'slevohlidka.search.recent';

/** Kolik posledních hledání si pamatovat. */
const RECENT_LIMIT = 5;

/** Znak, který patří ke slovu (po normalizaci) — před začátkem slova nesmí být. */
const WORD_CHAR = /[a-z0-9]/;

/**
 * Text pro porovnání: malá písmena bez diakritiky („Máslo“ → „maslo“).
 *
 * @param {string} text
 * @returns {string}
 */
export function normalizeSearch(text) {
    return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}

/**
 * Slova hledaného textu po normalizaci.
 *
 * @param {string} query
 * @returns {string[]}
 */
export function searchWords(query) {
    return normalizeSearch(query.trim()).split(/\s+/).filter(Boolean);
}

/**
 * Začíná slovo některé slovo textu? („cola“ v „coca-cola“ ano, „kola“ v „cokolada“ ne)
 *
 * @param {string} normalizedText Text po normalizeSearch
 * @param {string} word Slovo po normalizeSearch
 * @returns {boolean}
 */
export function startsWord(normalizedText, word) {
    let index = normalizedText.indexOf(word);
    while (index !== -1) {
        if (index === 0 || !WORD_CHAR.test(normalizedText[index - 1])) {
            return true;
        }
        index = normalizedText.indexOf(word, index + 1);
    }

    return false;
}

/**
 * Text rozdělený na části se zvýrazněnou shodou — slova hledání na začátcích slov textu.
 * Vue je vykreslí jako <mark>, bez v-html (text je od obchodu).
 *
 * @param {string} text
 * @param {string} query
 * @returns {{ text: string, match: boolean }[]}
 */
export function highlightParts(text, query) {
    const chars = Array.from(text);
    // Normalizovaný text znak po znaku — index v něm vede zpět na znak původního textu
    const normalizedChars = chars.map((char) => normalizeSearch(char));
    const normalized = normalizedChars.join('');
    const owner = normalizedChars.flatMap((part, index) => Array.from(part, () => index));
    const marked = new Array(chars.length).fill(false);

    for (const word of searchWords(query)) {
        let index = normalized.indexOf(word);
        while (index !== -1) {
            if (index === 0 || !WORD_CHAR.test(normalized[index - 1])) {
                for (let offset = 0; offset < word.length; offset++) {
                    marked[owner[index + offset]] = true;
                }
            }
            index = normalized.indexOf(word, index + 1);
        }
    }

    const parts = [];
    chars.forEach((char, index) => {
        const last = parts[parts.length - 1];
        if (last && last.match === marked[index]) {
            last.text += char;
        } else {
            parts.push({ text: char, match: marked[index] });
        }
    });

    return parts;
}

/**
 * Levenshteinova vzdálenost dvou slov — počet změn písmen (oprava překlepu).
 *
 * @param {string} a
 * @param {string} b
 * @returns {number}
 */
export function editDistance(a, b) {
    let previous = Array.from({ length: b.length + 1 }, (_, index) => index);
    for (let i = 1; i <= a.length; i++) {
        const current = [i];
        for (let j = 1; j <= b.length; j++) {
            current[j] = Math.min(previous[j] + 1, current[j - 1] + 1, previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        }
        previous = current;
    }

    return previous[b.length];
}

/**
 * Poslední hledání (nejnovější první).
 *
 * @returns {string[]}
 */
export function recentSearches() {
    const stored = readStored(RECENT_KEY, []);

    return Array.isArray(stored) ? stored.filter((item) => typeof item === 'string') : [];
}

/**
 * Zapamatuje si hledání — na začátek, bez opakování, nejvýš RECENT_LIMIT.
 *
 * @param {string} query
 */
export function rememberSearch(query) {
    const text = query.trim();
    if (!text) {
        return;
    }
    const others = recentSearches().filter((item) => normalizeSearch(item) !== normalizeSearch(text));
    writeStored(RECENT_KEY, [text, ...others].slice(0, RECENT_LIMIT));
}

/**
 * Zapomene jedno hledání, nebo bez argumentu všechna (i po odhlášení — lib/pwa.js).
 *
 * @param {string} [query]
 */
export function forgetSearch(query) {
    if (query === undefined) {
        writeStored(RECENT_KEY, null);

        return;
    }
    const remaining = recentSearches().filter((item) => item !== query);
    writeStored(RECENT_KEY, remaining.length ? remaining : null);
}
