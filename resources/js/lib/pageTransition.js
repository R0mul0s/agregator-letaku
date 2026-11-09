/**
 * Přechod mezi stránkami (R182): obsah se při přechodu na jinou stránku krátce prolne přes
 * View Transitions API (Inertia `viewTransition`), hlavička a spodní lišta zůstanou stát
 * (layout/_page-transition.scss). Jen pro přechod na jinou stránku — ne pro uložení, filtry
 * a obnovení části stránky, kde by prolnutí celé stránky rušilo a na chvíli blokovalo klepnutí.
 * Prohlížeč bez View Transitions a omezený pohyb přejdou jako dřív.
 *
 * @author Roman Hlaváček
 * @created 2026-11-09
 */
import { prefersReducedMotion } from '@/lib/scroll';

/** Metoda návštěvy, která načítá stránku (ostatní ukládají). */
const PAGE_METHOD = 'get';

/**
 * Je návštěva přechodem na jinou stránku? GET bez zachování stavu, bez částečného načtení
 * (`only`/`except`), ne asynchronní ani předběžné načtení.
 *
 * @param {object} options Volby návštěvy Inertie
 * @returns {boolean}
 */
function isPageChange(options) {
    return (
        String(options.method ?? PAGE_METHOD).toLowerCase() === PAGE_METHOD &&
        !options.preserveState &&
        !options.async &&
        !options.prefetch &&
        !options.only?.length &&
        !options.except?.length
    );
}

/**
 * Výchozí volby každé návštěvy Inertie (`defaults.visitOptions` v createInertiaApp): přechod na
 * jinou stránku s prolnutím, ostatní beze změny. Vrácené volby přebijí volby návštěvy.
 *
 * @param {string} href Adresa návštěvy
 * @param {object} options Volby návštěvy
 * @returns {object}
 */
export function pageVisitOptions(href, options) {
    return isPageChange(options) && !prefersReducedMotion() ? { viewTransition: true } : {};
}
