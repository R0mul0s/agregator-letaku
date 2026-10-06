/**
 * Vstupní bod frontendu — Inertia + Vue 3.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
import { initConsent } from '@/lib/consent';
import { initPwa } from '@/lib/pwa';
import { installStatusToasts } from '@/lib/toast';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

/** Název aplikace ze šablony (meta application-name) — text z lang/cs/app.php. */
const APP_NAME = document.querySelector('meta[name="application-name"]')?.content ?? '';

/** Třída kořene aplikace — rozvržení stránky přes celou výšku okna (layout/_page.scss). */
const ROOT_CLASS = 'app-root';

/** Obsah stránky ze serveru pro roboty (resources/views/seo/content.blade.php). */
const SEO_CONTENT_SELECTOR = '[data-seo-content]';

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
        // Souhlas s cookies (R52) dřív než cokoli dalšího — měření se spustí jen po souhlasu
        initConsent(props.initialPage.props.cookieConsent);
        el.classList.add(ROOT_CLASS);
        // Obsah ze serveru pro roboty bez JavaScriptu (R94) — aplikace ukáže svůj, čtečka by četla dvakrát
        document.querySelector(SEO_CONTENT_SELECTOR)?.remove();
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
        installStatusToasts(props.initialPage);
        // Aplikace v telefonu (R66): service worker, offline režim, výzva k přidání na plochu
        initPwa(props.initialPage);
    },
});
