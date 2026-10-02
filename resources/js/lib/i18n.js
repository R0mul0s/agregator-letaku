/**
 * Překlady UI ve Vue komponentách.
 *
 * Texty posílá server ze skupiny `app.ui` (lang/cs/app.php) jako sdílenou
 * Inertia vlastnost `translations`. Do Vue se nikdy nepíšou natvrdo.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
import { usePage } from '@inertiajs/vue3';

/** Oddělovač tvarů plurálu v textu („z :count dne|z :count dnů|z :count dní"). */
const PLURAL_SEPARATOR = '|';

/**
 * Pořadí tvarů v textu podle kategorie Intl.PluralRules — pro češtinu
 * 1 / 2–4 / ostatní. Chybějící tvar nahradí poslední uvedený.
 */
const PLURAL_FORM_INDEX = { one: 0, few: 1, many: 2, other: 2 };

/**
 * Vybere tvar plurálu podle počtu, text bez „|" vrátí beze změny.
 *
 * @param {string} text
 * @param {number|undefined} count
 * @param {string} locale
 * @returns {string}
 */
function choosePluralForm(text, count, locale) {
    if (!text.includes(PLURAL_SEPARATOR) || typeof count !== 'number') {
        return text;
    }

    const forms = text.split(PLURAL_SEPARATOR);
    const index = PLURAL_FORM_INDEX[new Intl.PluralRules(locale).select(count)] ?? forms.length - 1;

    return forms[Math.min(index, forms.length - 1)];
}

/**
 * Vrátí funkci t(key, replacements) nad aktuálními překlady.
 * Chybějící klíč vrací jako holý text — stejně jako Laravel, je to chyba k opravě.
 * Text s tvary oddělenými „|" vybere tvar podle `replacements.count`.
 *
 * @returns {(key: string, replacements?: Record<string, string|number>) => string}
 */
export function useTranslations() {
    const page = usePage();

    return (key, replacements = {}) => {
        const text = key
            .split('.')
            .reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), page.props.translations);

        if (typeof text !== 'string') {
            return key;
        }

        // Od nejdelšího názvu — jinak by „:count" rozbil „:countdown" (stejně jako Laravel)
        return Object.entries(replacements)
            .sort(([a], [b]) => b.length - a.length)
            .reduce(
                (result, [name, value]) => result.replaceAll(`:${name}`, String(value)),
                choosePluralForm(text, replacements.count, page.props.locale),
            );
    };
}
