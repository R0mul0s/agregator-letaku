/**
 * Posun stránky — návrat na začátek (tlačítko Nahoru s košíkem, R92).
 *
 * @author Roman Hlaváček
 * @created 2026-10-06
 */

/**
 * Má prohlížeč omezený pohyb (prefers-reduced-motion)?
 *
 * @returns {boolean}
 */
export function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/** Způsob posunu: plynule, při omezeném pohybu skokem. */
function scrollBehavior() {
    return prefersReducedMotion() ? 'auto' : 'smooth';
}

/** Vyjede na začátek stránky (plynule, při omezeném pohybu skokem). */
export function scrollToTop() {
    window.scrollTo({ top: 0, behavior: scrollBehavior() });
}

/**
 * Posune stránku k prvku (plynule, při omezeném pohybu skokem).
 *
 * @param {Element|null|undefined} element
 * @param {ScrollLogicalPosition} block Kam prvek zarovnat (start, nearest…)
 */
export function scrollIntoViewGently(element, block = 'start') {
    element?.scrollIntoView({ behavior: scrollBehavior(), block });
}
