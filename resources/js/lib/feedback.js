/**
 * Zpětná vazba po úspěšné akci (R182): krátké zavibrování telefonu (jen Android, iPhone
 * vibraci webu nedovolí) a poskočení záložky nákupního seznamu po přidání akce, ať je vidět,
 * kam akce zmizela. Vzhled poskočení v layout/_tab-bar.scss.
 *
 * @author Roman Hlaváček
 * @created 2026-11-09
 */
import { ref } from 'vue';

/** Délka zavibrování po akci v ms — jen náznak, ne upozornění. */
const TAP_VIBRATION_MS = 12;

/** Kolikrát se do nákupního seznamu přidala akce — záložka seznamu poskočí při každé změně. */
export const shoppingListBumps = ref(0);

/** Krátce zavibruje telefonem (volat jen z klepnutí — jinak to prohlížeč nedovolí). */
export function vibrateTap() {
    navigator.vibrate?.(TAP_VIBRATION_MS);
}

/** Akce přibyla do nákupního seznamu: zavibruje a záložka seznamu poskočí. */
export function bumpShoppingList() {
    shoppingListBumps.value += 1;
    vibrateTap();
}
