/**
 * Výběr v seznamu návrhů (role listbox) klávesnicí — šipky posouvají dokola, vybraná volba
 * je `aria-activedescendant` pole. Sdílí ho hledání (SearchSuggest) a přidání hlídané položky
 * (WatchAdd), ať oprava přístupnosti platí v obou (R99, R113).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import { computed, ref, toValue } from 'vue';

/**
 * Stav výběru v seznamu.
 *
 * @param {import('vue').MaybeRefOrGetter<any[]>} options Volby seznamu
 * @param {import('vue').MaybeRefOrGetter<string>} listId ID seznamu — volba má ID `${listId}-${index}`
 * @returns {{ activeIndex: import('vue').Ref<number>, activeId: import('vue').ComputedRef<string|undefined>, activeOption: import('vue').ComputedRef<any>, moveByArrow: (event: KeyboardEvent) => boolean }}
 */
export function useListbox(options, listId) {
    /** Vybraná volba, -1 = žádná. */
    const activeIndex = ref(-1);
    const activeId = computed(() => (activeIndex.value >= 0 ? `${toValue(listId)}-${activeIndex.value}` : undefined));
    const activeOption = computed(() => (activeIndex.value >= 0 ? toValue(options)[activeIndex.value] : undefined));

    /**
     * Šipka dolů / nahoru posune výběr dokola. Vrátí true, když klávesu zpracovala a volby
     * jsou — bez voleb šipka dál posouvá kurzor v poli.
     *
     * @param {KeyboardEvent} event
     * @returns {boolean}
     */
    function moveByArrow(event) {
        const count = toValue(options).length;
        if ((event.key !== 'ArrowDown' && event.key !== 'ArrowUp') || count === 0) {
            return false;
        }
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        activeIndex.value = (activeIndex.value + step + count) % count;

        return true;
    }

    return { activeIndex, activeId, activeOption, moveByArrow };
}
