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

/** Vyjede na začátek stránky (plynule, při omezeném pohybu skokem). */
export function scrollToTop() {
    window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
}
