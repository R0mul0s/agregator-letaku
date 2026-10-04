/**
 * Service worker Slevohlídky — aplikace v telefonu (R66).
 *
 * Není součástí buildu Vite: musí mít stálou adresu /sw.js v kořeni webu. Server
 * (ServiceWorkerController) před skript doplní `self.SW_CONFIG`: verzi buildu, soubory
 * k uložení, stránky dostupné offline a časový limit sítě. Nový build = nová verze =
 * prohlížeč service worker vymění a staré soubory smaže.
 *
 * - Soubory buildu, písmo a obrázky webu: nejdřív z cache (jména souborů buildu mají otisk).
 * - Stránky z `offlinePaths` (Moje slevy, nákupní seznam, Hlídám): nejdřív ze sítě, uloží se;
 *   bez připojení nebo po `networkTimeoutMs` bez odpovědi poslední uložená verze. Platí pro
 *   celé načtení (HTML) i přechody Inertie (JSON s hlavičkou X-Inertia) — každé zvlášť.
 * - Ostatní stránky bez připojení: stránka „Jste offline“. Přechod Inertie bez připojení
 *   skončí chybou sítě a aplikace ukáže toast (resources/js/lib/pwa.js).
 * - Upozornění v telefonu (web push): ukáže upozornění a číslo na ikoně, klepnutí otevře aplikaci.
 *
 * Uložené stránky obsahují data uživatele — při odhlášení je smaže aplikace (clearOfflineData).
 *
 * @author Roman Hlaváček
 * @created 2026-10-04
 */

const CONFIG = self.SW_CONFIG;

/** Soubory buildu a obrázky — jedna cache na verzi, starší se při aktivaci smažou. */
const STATIC_PREFIX = 'slevohlidka-static-';
const STATIC_CACHE = `${STATIC_PREFIX}${CONFIG.version}`;

/** Stránky s daty uživatele — název musí sedět s resources/js/lib/pwa.js (PAGES_CACHE). */
const PAGES_CACHE = 'slevohlidka-pages';

/** Hlavička, podle které server i service worker poznají přechod Inertie. */
const INERTIA_HEADER = 'X-Inertia';

/** Částečné načtení Inertie (jen některé vlastnosti stránky) se neukládá — chyběla by data. */
const PARTIAL_HEADER = 'X-Inertia-Partial-Component';

/** Kdy byla uložená stránka stažená — aplikace ho ukáže v liště „Jste offline“. */
const FETCHED_AT_HEADER = 'X-Slevohlidka-Fetched-At';

/** Parametr klíče cache pro JSON Inertie — HTML a JSON stejné adresy se ukládají zvlášť. */
const INERTIA_CACHE_PARAM = '__inertia';

/** Cesty souborů, které se mění jen s novou verzí (build má otisk ve jméně). */
const STATIC_PATH_PREFIXES = ['/build/', '/images/'];

/**
 * Adresy, které service worker naposledy obsloužil z cache, s časem uložení — aplikace se
 * ptá zprávou `offline-status`. Jen v paměti: po uspání service workeru se zapomene a lišta
 * se řídí jen tím, jestli je prohlížeč online.
 */
const servedFromCache = new Map();

self.addEventListener('install', (event) => {
    // Chybějící soubor nesmí zablokovat instalaci — stáhne se při prvním použití
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => Promise.allSettled(CONFIG.precache.map((url) => cache.add(url))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith(STATIC_PREFIX) && key !== STATIC_CACHE).map((key) => caches.delete(key))))
            .then(deleteStoredDocuments)
            .then(() => self.clients.claim()),
    );
});

/**
 * Smaže uložené celé stránky (HTML) — odkazují na soubory staré verze, které už v cache
 * nejsou. JSON Inertie na verzi nezávisí a zůstane; HTML aplikace uloží znovu (warm-pages).
 *
 * @returns {Promise<void>}
 */
async function deleteStoredDocuments() {
    const cache = await caches.open(PAGES_CACHE);
    const keys = await cache.keys();
    await Promise.all(keys.filter((key) => !new URL(key.url).searchParams.has(INERTIA_CACHE_PARAM)).map((key) => cache.delete(key)));
}

/**
 * Uloží stránky dostupné offline předem — nákupní seznam je pak v obchodě k dispozici,
 * i když ho uživatel od poslední změny neotevřel. Přesměrování (vypršelé přihlášení) se neuloží.
 *
 * @returns {Promise<void>}
 */
async function warmPages() {
    const cache = await caches.open(PAGES_CACHE);
    await Promise.allSettled(
        CONFIG.offlinePaths.map(async (path) => {
            const url = new URL(path, self.location.origin).href;
            const response = await fetch(url, { credentials: 'same-origin' });
            if (isCacheable(response)) {
                await cache.put(url, await withFetchedAt(response));
            }
        }),
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Odeslání formulářů, obrázky z CDN obchodů a měření jdou přímo na síť
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    const offlinePage = CONFIG.offlinePaths.includes(url.pathname);

    if (request.mode === 'navigate') {
        event.respondWith(offlinePage ? networkFirst(event, url.href) : networkOrOfflinePage(request));

        return;
    }

    if (request.headers.has(INERTIA_HEADER)) {
        if (offlinePage && !request.headers.has(PARTIAL_HEADER)) {
            event.respondWith(networkFirst(event, inertiaCacheKey(url)));
        }

        return;
    }

    if (STATIC_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        event.respondWith(cacheFirst(request));
    }
});

/**
 * Klíč cache pro JSON Inertie dané adresy.
 *
 * @param {URL} url
 * @returns {string}
 */
function inertiaCacheKey(url) {
    const key = new URL(url.href);
    key.searchParams.set(INERTIA_CACHE_PARAM, '1');

    return key.href;
}

/**
 * Soubor z cache, jinak ze sítě (a uloží se na příště).
 *
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (isCacheable(response)) {
        const cache = await caches.open(STATIC_CACHE);
        await cache.put(request, response.clone());
    }

    return response;
}

/**
 * Stránka ze sítě; bez připojení stránka „Jste offline“.
 *
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function networkOrOfflinePage(request) {
    try {
        return await fetch(request);
    } catch {
        return offlineFallback(request);
    }
}

/**
 * Stránka dostupná offline: ze sítě a uloží se. Bez připojení nebo bez odpovědi do časového
 * limitu uložená verze — nová se i tak uloží na příště.
 *
 * @param {FetchEvent} event
 * @param {string} cacheKey
 * @returns {Promise<Response>}
 */
async function networkFirst(event, cacheKey) {
    const { request } = event;
    const path = new URL(request.url).pathname;
    const cache = await caches.open(PAGES_CACHE);

    const network = fetch(request).then(async (response) => {
        if (isCacheable(response)) {
            await cache.put(cacheKey, await withFetchedAt(response.clone()));
        }

        return response;
    });
    // Uložení doběhne, i když uživatel mezitím dostal uloženou verzi
    event.waitUntil(network.catch(() => undefined));

    const cached = await cache.match(cacheKey, { ignoreVary: true });
    if (!cached) {
        try {
            const response = await network;
            servedFromCache.delete(path);

            return response;
        } catch {
            return offlineFallback(request);
        }
    }

    const fromCache = () => {
        servedFromCache.set(path, cached.headers.get(FETCHED_AT_HEADER));

        return cached;
    };

    try {
        const response = await Promise.race([network, delay(CONFIG.networkTimeoutMs)]);
        if (!response) {
            return fromCache();
        }
        servedFromCache.delete(path);

        return response;
    } catch {
        return fromCache();
    }
}

/**
 * Náhrada bez připojení: celé načtení dostane stránku „Jste offline“, přechod Inertie chybu
 * sítě (Inertia ji ohlásí aplikaci).
 *
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function offlineFallback(request) {
    if (request.mode === 'navigate') {
        const page = await caches.match(CONFIG.offlineUrl);
        if (page) {
            return page;
        }
    }

    return Response.error();
}

/**
 * Jde odpověď uložit? Jen úspěšná, vlastní a bez přesměrování (přesměrování na přihlášení
 * po vypršení relace by se jinak ukazovalo místo stránky).
 *
 * @param {Response} response
 * @returns {boolean}
 */
function isCacheable(response) {
    return response.ok && !response.redirected && response.type === 'basic';
}

/**
 * Kopie odpovědi s časem stažení v hlavičce.
 *
 * @param {Response} response
 * @returns {Promise<Response>}
 */
async function withFetchedAt(response) {
    const headers = new Headers(response.headers);
    headers.set(FETCHED_AT_HEADER, new Date().toISOString());

    return new Response(await response.blob(), { status: response.status, statusText: response.statusText, headers });
}

/**
 * Promise, který se po `ms` splní s null.
 *
 * @param {number} ms
 * @returns {Promise<null>}
 */
function delay(ms) {
    return new Promise((resolve) => setTimeout(() => resolve(null), ms));
}

// Zprávy z aplikace (resources/js/lib/pwa.js): jestli stránku dostala z cache (lišta „Jste
// offline“ s časem uložení) a uložení stránek předem po přihlášení
self.addEventListener('message', (event) => {
    if (event.data?.type === 'offline-status') {
        event.ports[0]?.postMessage({ fetchedAt: servedFromCache.get(event.data.path) ?? null });
    } else if (event.data?.type === 'warm-pages') {
        event.waitUntil(warmPages());
    }
});

// Upozornění v telefonu (web push) — obsah skládá server (App\Domain\Push\PushMessage)
self.addEventListener('push', (event) => {
    const message = event.data ? event.data.json() : {};
    const shown = self.registration.showNotification(message.title, {
        body: message.body,
        icon: CONFIG.notification.icon,
        badge: CONFIG.notification.badge,
        tag: message.tag,
        renotify: Boolean(message.tag),
        lang: 'cs',
        data: { url: message.url },
    });
    // Číslo na ikoně aplikace (jen tam, kde to systém umí)
    const badge = message.badge && self.navigator.setAppBadge ? self.navigator.setAppBadge(message.badge).catch(() => undefined) : Promise.resolve();

    event.waitUntil(Promise.all([shown, badge]));
});

// Klepnutí na upozornění: otevřená aplikace přejde na stránku sama (Inertia), jinak nové okno
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url ?? '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const client = windows.find((window) => new URL(window.url).origin === self.location.origin);
            if (!client) {
                return self.clients.openWindow(url);
            }
            client.postMessage({ type: 'navigate', url });

            return client.focus();
        }),
    );
});
