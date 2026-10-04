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

    /** Požádá o zámek displeje; odmítnutí (úsporný režim) nevadí. */
    async function acquire() {
        if (!enabled.value || sentinel || document.visibilityState !== 'visible') {
            return;
        }
        try {
            sentinel = await navigator.wakeLock.request('screen');
            sentinel.addEventListener('release', () => (sentinel = null));
        } catch {
            sentinel = null;
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
        if (supported) {
            document.addEventListener('visibilitychange', acquire);
            acquire();
        }
    });

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', acquire);
        release();
    });

    return { supported, enabled, toggle };
}
