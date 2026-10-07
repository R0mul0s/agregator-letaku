/**
 * Rozbalené skupiny Mých slev (R43, R55, R101) — vytaženo z Home.vue (R106). Ve výchozím
 * stavu je vše sbalené a rozbalené skupiny i sekce pod nimi (Brzy, Zatím bez akce) si pamatuje
 * prohlížeč. Při výběru obchodu nebo štítku („soustředěný“ pohled) je naopak vše rozbalené
 * a sbalené se nepamatuje — po zrušení výběru se vrátí zapamatovaný stav.
 *
 * @author Roman Hlaváček
 * @created 2026-10-07
 */
import { readStored, writeStored } from '@/lib/storage';
import { computed, ref } from 'vue';

/**
 * Stav rozbalení skupin.
 *
 * @param {{
 *     storageKey: string,
 *     sectionIds: string[],
 *     focused: import('vue').Ref<boolean>,
 *     visibleIds: import('vue').Ref<number[]>,
 * }} options storageKey = klíč localStorage, sectionIds = sekce pod skupinami (ve stejném klíči),
 *     focused = výběr obchodu nebo štítek, visibleIds = skupiny, které jsou teď vidět
 */
export function useExpandedGroups({ storageKey, sectionIds, focused, visibleIds }) {
    /** Rozbalené skupiny a sekce (zapamatované); ve výchozím stavu je vše sbalené. */
    const expandedIds = ref(new Set());

    /** V soustředěném pohledu jsou skupiny rozbalené; sbalené klepnutím (bez zapamatování). */
    const focusCollapsedIds = ref(new Set());

    /** Uloží rozbalené skupiny; bez localStorage platí jen do zavření stránky. */
    function save() {
        writeStored(storageKey, [...expandedIds.value]);
    }

    /**
     * Množina s přidaným nebo odebraným prvkem (nová, kvůli reaktivitě).
     *
     * @param {Set} set
     * @param {number|string} id
     * @param {boolean} present
     * @returns {Set}
     */
    function withMember(set, id, present) {
        const next = new Set(set);
        if (present) {
            next.add(id);
        } else {
            next.delete(id);
        }

        return next;
    }

    /**
     * Je skupina rozbalená?
     *
     * @param {number} id
     * @returns {boolean}
     */
    function isExpanded(id) {
        return focused.value ? !focusCollapsedIds.value.has(id) : expandedIds.value.has(id);
    }

    /** Jsou rozbalené všechny viditelné skupiny? */
    const allExpanded = computed(() => visibleIds.value.every(isExpanded));

    /**
     * Rozbalí nebo sbalí jednu skupinu.
     *
     * @param {number} id
     * @param {boolean} value
     */
    function setExpanded(id, value) {
        if (focused.value) {
            focusCollapsedIds.value = withMember(focusCollapsedIds.value, id, !value);

            return;
        }
        expandedIds.value = withMember(expandedIds.value, id, value);
        save();
    }

    /**
     * Rozbalí všechny viditelné skupiny, nebo — když už jsou všechny rozbalené — všechny sbalí.
     * Sekce pod skupinami zůstanou, jak jsou.
     */
    function toggleAll() {
        if (focused.value) {
            focusCollapsedIds.value = new Set(allExpanded.value ? visibleIds.value : []);

            return;
        }

        const next = new Set(allExpanded.value ? [] : visibleIds.value);
        for (const sectionId of sectionIds) {
            if (expandedIds.value.has(sectionId)) {
                next.add(sectionId);
            }
        }
        expandedIds.value = next;
        save();
    }

    /** Nový výběr obchodu nebo štítku začne se vším rozbaleným. */
    function resetFocus() {
        focusCollapsedIds.value = new Set();
    }

    /**
     * Rozbalení sekce pod skupinami jako v-model — v soustředěném pohledu se sekce neukazují,
     * proto vždy mezi zapamatovanými.
     *
     * @param {string} sectionId
     * @returns {import('vue').WritableComputedRef<boolean>}
     */
    function section(sectionId) {
        return computed({
            get: () => expandedIds.value.has(sectionId),
            set: (value) => {
                expandedIds.value = withMember(expandedIds.value, sectionId, value);
                save();
            },
        });
    }

    /** Načte zapamatovaný stav (po připojení stránky, jako ostatní pohodlí prohlížeče). */
    function restore() {
        const stored = readStored(storageKey, []);
        expandedIds.value = new Set(Array.isArray(stored) ? stored : []);
    }

    return { isExpanded, allExpanded, setExpanded, toggleAll, resetFocus, section, restore };
}
