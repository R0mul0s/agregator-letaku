/**
 * Direktiva `v-fade-in` (R182): fotka akce se po načtení plynule objeví, místo aby naskočila.
 * Fotka už načtená (z mezipaměti) se ukáže hned; nenačtená (i líně, `loading="lazy"`) je do
 * načtení průhledná. Po chybě se ukáže taky — komponenta pak ukáže náhradu (siluetu maskota).
 * Bez JavaScriptu třída chybí a fotka je vidět normálně. Vzhled v components/_fade-in.scss.
 *
 * @author Roman Hlaváček
 * @created 2026-11-09
 */

/** Třída fotky, která se plynule objevuje. */
const FADE_CLASS = 'fade-in';

/** Třída fotky, která se ještě načítá (průhledná). */
const LOADING_CLASS = 'fade-in--loading';

/**
 * Nenačtenou fotku skryje do načtení nebo chyby.
 *
 * @param {HTMLImageElement} image
 */
function watchLoading(image) {
    if (image.complete) {
        image.classList.remove(LOADING_CLASS);

        return;
    }
    image.classList.add(LOADING_CLASS);
    const reveal = () => image.classList.remove(LOADING_CLASS);
    image.addEventListener('load', reveal, { once: true });
    image.addEventListener('error', reveal, { once: true });
}

/** @type {import('vue').ObjectDirective<HTMLImageElement>} */
export const fadeInImage = {
    /**
     * Fotka v dokumentu: třída pro přechod a skrytí do načtení.
     *
     * @param {HTMLImageElement} image
     */
    mounted(image) {
        image.classList.add(FADE_CLASS);
        watchLoading(image);
    },

    /**
     * Stejný prvek s jinou fotkou (výpis přepoužil kartu) — znovu do načtení.
     *
     * @param {HTMLImageElement} image
     * @param {import('vue').DirectiveBinding} binding
     * @param {import('vue').VNode} vnode
     * @param {import('vue').VNode} oldVnode
     */
    updated(image, binding, vnode, oldVnode) {
        if (vnode.props?.src !== oldVnode.props?.src) {
            watchLoading(image);
        }
    },
};
