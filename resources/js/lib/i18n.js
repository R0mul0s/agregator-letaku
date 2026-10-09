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

/** Pravidla plurálu podle jazyka — konstrukce je drahá a t() se volá stokrát za vykreslení. */
const pluralRulesByLocale = new Map();

/**
 * Intl.PluralRules pro jazyk, vytvořená jen jednou.
 *
 * @param {string} locale
 * @returns {Intl.PluralRules}
 */
function pluralRules(locale) {
    if (!pluralRulesByLocale.has(locale)) {
        pluralRulesByLocale.set(locale, new Intl.PluralRules(locale));
    }

    return pluralRulesByLocale.get(locale);
}

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
    const index = PLURAL_FORM_INDEX[pluralRules(locale).select(count)] ?? forms.length - 1;

    return forms[Math.min(index, forms.length - 1)];
}

/**
 * Text pod klíčem („toast.messages.digest-saved“) v překladech, nebo null, když chybí.
 * Pro místa mimo komponentu (posluchač událostí routeru), kde useTranslations nejde.
 *
 * @param {object} translations Sdílená vlastnost `translations`
 * @param {string} key
 * @returns {string|null}
 */
export function lookup(translations, key) {
    const text = key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), translations);

    return typeof text === 'string' ? text : null;
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
        const text = lookup(page.props.translations, key);

        if (text === null) {
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
