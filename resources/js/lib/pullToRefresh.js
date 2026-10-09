/**
 * Stažení stránky dolů pro nové načtení v aplikaci z plochy (R112). Chrome to v prohlížeči
 * umí sám, aplikace z plochy (`display: standalone`) na Androidu ani na iPhonu ne — gesto
 * tu dělá stránka: tah dolů na začátku stránky ukáže ukazatel, po puštění za hranicí se
 * stránka načte znovu.
 *
 * Gesto se nespustí uvnitř otevřeného okna (`<dialog>`), v hledání přes celou obrazovku,
 * v prvku, který je sám posunutý (seznam v okně), ani při vodorovném tahu (štítky filtrů).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/** Jak daleko (px, po zpomalení) musí ukazatel sjet, aby puštění stránku načetlo. */
const THRESHOLD_PX = 70;

/** Dál ukazatel nesjede, i když prst pokračuje. */
const MAX_DISTANCE_PX = 110;

/** Ukazatel jede pomaleji než prst — jako tah za gumu. */
const RESISTANCE = 0.5;

/** Po kolika px pohybu se rozhodne, jestli jde o tah dolů, nebo o vodorovný či opačný pohyb. */
const DIRECTION_LOCK_PX = 10;

/** Hledání přes celou obrazovku na telefonu (SearchSuggest.vue). */
const SEARCH_SHEET_CLASS = 'has-search-sheet';

/**
 * Je prvek pod prstem (nebo některý jeho předek) sám posunutý dolů? Tah pak patří jemu.
 *
 * @param {EventTarget|null} target
 * @returns {boolean}
 */
function insideScrolledElement(target) {
    for (let element = target instanceof Element ? target : null; element && element !== document.body; element = element.parentElement) {
        if (element.scrollTop > 0) {
            return true;
        }
    }

    return false;
}

/**
 * Je otevřené okno nebo hledání přes celou obrazovku?
 *
 * @returns {boolean}
 */
function overlayOpen() {
    return document.querySelector('dialog[open]') !== null || document.documentElement.classList.contains(SEARCH_SHEET_CLASS);
}

/**
 * Gesto na celém dokumentu.
 *
 * @param {{ enabled: () => boolean, refresh: () => Promise<unknown> }} options enabled = má gesto
 *     teď fungovat (aplikace z plochy), refresh = načte stránku znovu, splní se po dokončení
 * @returns {{ distance: import('vue').Ref<number>, ready: import('vue').ComputedRef<boolean>, refreshing: import('vue').Ref<boolean> }}
 *     distance = posun ukazatele v px, ready = puštění teď stránku načte, refreshing = načítá se
 */
export function usePullToRefresh({ enabled, refresh }) {
    const distance = ref(0);
    const refreshing = ref(false);
    const ready = computed(() => distance.value >= THRESHOLD_PX);

    let tracking = false;
    let pulling = false;
    let startX = 0;
    let startY = 0;

    /** @param {TouchEvent} event */
    function onStart(event) {
        if (refreshing.value || event.touches.length !== 1 || window.scrollY > 0 || overlayOpen() || insideScrolledElement(event.target)) {
            return;
        }
        tracking = true;
        pulling = false;
        startX = event.touches[0].clientX;
        startY = event.touches[0].clientY;
    }

    /** @param {TouchEvent} event */
    function onMove(event) {
        if (!tracking) {
            return;
        }

        const dx = event.touches[0].clientX - startX;
        const dy = event.touches[0].clientY - startY;
        if (!pulling) {
            if (Math.abs(dx) < DIRECTION_LOCK_PX && Math.abs(dy) < DIRECTION_LOCK_PX) {
                return;
            }
            // Tah nahoru, vodorovně nebo stránka mezitím odjela — gesto to není
            if (dy <= 0 || Math.abs(dx) > dy || window.scrollY > 0) {
                tracking = false;

                return;
            }
            pulling = true;
        }

        // Bez toho by iPhone stránku pružně odtáhl a Android ukázal záři okraje
        event.preventDefault();
        distance.value = Math.min(MAX_DISTANCE_PX, Math.max(0, dy * RESISTANCE));
    }

    async function onEnd() {
        const wasPulling = tracking && pulling;
        tracking = false;
        pulling = false;
        if (!wasPulling) {
            return;
        }

        if (!ready.value) {
            distance.value = 0;

            return;
        }

        refreshing.value = true;
        distance.value = THRESHOLD_PX;
        try {
            await refresh();
        } finally {
            refreshing.value = false;
            distance.value = 0;
        }
    }

    function listen() {
        document.addEventListener('touchstart', onStart, { passive: true });
        // Ne pasivní — během tahu se posun stránky zastaví (preventDefault)
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onEnd, { passive: true });
        document.addEventListener('touchcancel', onEnd, { passive: true });
    }

    function stopListening() {
        document.removeEventListener('touchstart', onStart);
        document.removeEventListener('touchmove', onMove);
        document.removeEventListener('touchend', onEnd);
        document.removeEventListener('touchcancel', onEnd);
    }

    // Jen v aplikaci z plochy — nepasivní posluchač tahu by v prohlížeči zpomalil posouvání.
    // Stav se zjistí až po spuštění aplikace (initPwa), proto watch, ne onMounted.
    watch(enabled, (on) => (on ? listen() : stopListening()), { immediate: true });
    onBeforeUnmount(stopListening);

    return { distance, ready, refreshing };
}
