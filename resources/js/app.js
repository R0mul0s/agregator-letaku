/**
 * Vstupní bod frontendu — Inertia + Vue 3.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
// Písmo Nunito (zaoblené jako nápis v logu) — variabilní, latinka i s češtinou, z balíčku, ne z CDN
import '@fontsource-variable/nunito/wght.css';
import { installStatusToasts } from '@/lib/toast';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

/** Název aplikace ze šablony (meta application-name) — text z lang/cs/app.php. */
const APP_NAME = document.querySelector('meta[name="application-name"]')?.content ?? '';

createInertiaApp({
    // „Účet · Slevohlídka"; stránka bez titulku dostane jen název aplikace, titulek
    // s názvem aplikace (úvodní stránka) se nezdvojí
    title: (title) => (title && APP_NAME && !title.includes(APP_NAME) ? `${title} · ${APP_NAME}` : title || APP_NAME),
    // Stránky se načítají líně — každá má vlastní chunk.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');

        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
        installStatusToasts(props.initialPage);
    },
});
