/**
 * Nezhasínání displeje v nákupním seznamu (Screen Wake Lock, R66) — v obchodě s plnýma
 * rukama displej nezhasne mezi dvěma odškrtnutími. Volba se pamatuje v prohlížeči; zámek
 * systém uvolní, když aplikace přejde do pozadí, a po návratu se obnoví.
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */
import { readStored, writeStored } from '@/lib/storage';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Klíč v localStorage: zapnuté nezhasínání displeje (zásady, kap. 5). */
const STORAGE_KEY = 'slevohlidka.shopping.wake_lock';

/**
 * Přepínač nezhasínání displeje pro stránku — zámek drží jen, dokud je stránka otevřená.
 *
 * @returns {{ supported: boolean, enabled: import('vue').Ref<boolean>, toggle: () => void }}
 */
export function useWakeLock() {
    const supported = 'wakeLock' in navigator;
    const enabled = ref(supported && readStored(STORAGE_KEY, false) === true);
    let sentinel = null;
    /** Žádost o zámek, na kterou se čeká — druhá souběžná by držela druhý zámek. */
    let requesting = false;
    /** Stránka je otevřená — po odchodu se zámek, který dorazí pozdě, hned uvolní. */
    let mounted = false;

    /** Požádá o zámek displeje; odmítnutí (úsporný režim) nevadí. */
    async function acquire() {
        if (!enabled.value || sentinel || requesting || document.visibilityState !== 'visible') {
            return;
        }
        requesting = true;
        try {
            const lock = await navigator.wakeLock.request('screen');
            // Mezitím vypnuto nebo stránka opuštěná (R113) — jinak by displej svítil dál
            if (!enabled.value || !mounted) {
                lock.release().catch(() => undefined);

                return;
            }
            sentinel = lock;
            sentinel.addEventListener('release', () => (sentinel = null));
        } catch {
            sentinel = null;
        } finally {
            requesting = false;
        }
    }

    /** Uvolní zámek displeje. */
    function release() {
        sentinel?.release().catch(() => undefined);
        sentinel = null;
    }

    /** Zapne nebo vypne nezhasínání a zapamatuje si volbu. */
    function toggle() {
        enabled.value = !enabled.value;
        writeStored(STORAGE_KEY, enabled.value || null);
        if (enabled.value) {
            acquire();
        } else {
            release();
        }
    }

    onMounted(() => {
        mounted = true;
        if (supported) {
            document.addEventListener('visibilitychange', acquire);
            acquire();
        }
    });

    onBeforeUnmount(() => {
        mounted = false;
        document.removeEventListener('visibilitychange', acquire);
        release();
    });

    return { supported, enabled, toggle };
}
