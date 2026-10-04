/**
 * Souhlas s cookies (R52) a Google Analytics 4 podle něj.
 *
 * Volba se ukládá do cookie `slevohlidka-consent` (verze, kategorie, čas) na dobu
 * z konfigurace (letaky.cookie_consent). Bez platné volby se ukáže lišta (CookieConsent.vue).
 * Google Analytics se **načte až po souhlasu s analytickými cookies** — do té doby se
 * na Google nic neposílá. Google Consent Mode v2: výchozí stav vše „denied“, po volbě
 * „update“; marketingový souhlas povolí reklamní signály Googlu (ad_*).
 * Inline skript CSP nedovolí — gtag se nastavuje tady, knihovna z googletagmanager.com.
 * Zobrazení stránek posílá aplikace sama po každém přechodu Inertie (R69), s adresou bez
 * tokenů: odkaz na obnovu hesla nese token a e-mail, odkaz na odhlášení z e-mailů podpis —
 * do Googlu nesmí. Měření změn historie v GA4 (rozšířené měření) proto musí být vypnuté.
 *
 * @author Roman Hlaváček
 * @created 2026-10-03
 */
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/** Název cookie se souhlasem — popsaný v zásadách (resources/legal/privacy.md). */
const CONSENT_COOKIE = 'slevohlidka-consent';

/** Cookies Google Analytics (_ga, _ga_<ID>) — po odvolání souhlasu se smažou. */
const ANALYTICS_COOKIE_PREFIX = '_ga';

const SECONDS_PER_DAY = 86400;

const GTAG_URL = 'https://www.googletagmanager.com/gtag/js';

/** Stav souhlasu pro lištu a nastavení. */
export const consentState = reactive({
    /** Uživatel už vybral (platná cookie se stejnou verzí). */
    decided: false,
    analytics: false,
    marketing: false,
    /** Otevřené okno s nastavením kategorií. */
    settingsOpen: false,
});

/** Konfigurace ze serveru (sdílený prop cookieConsent). */
let config = { measurementId: null, version: 1, maxAgeDays: 180, redactedPaths: [] };

let analyticsLoaded = false;

/** Adresa naposledy změřené stránky — počáteční načtení se nezměří dvakrát. */
let lastMeasuredLocation = null;

/**
 * Fronta příkazů gtag — musí to být objekt arguments, ne pole (tak ho gtag.js čte).
 */
function gtag() {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(arguments);
}

/**
 * Stav Consent Mode podle volby uživatele.
 *
 * @param {{ analytics: boolean, marketing: boolean }} choice
 * @returns {Record<string, 'granted'|'denied'>}
 */
function consentModeFor({ analytics, marketing }) {
    const state = (granted) => (granted ? 'granted' : 'denied');

    return {
        analytics_storage: state(analytics),
        ad_storage: state(marketing),
        ad_user_data: state(marketing),
        ad_personalization: state(marketing),
    };
}

/**
 * Přečte uloženou volbu; jiná verze souhlasu = jako by žádná nebyla (zeptat se znovu).
 *
 * @returns {{ analytics: boolean, marketing: boolean } | null}
 */
function readStoredChoice() {
    const raw = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${CONSENT_COOKIE}=`))
        ?.slice(CONSENT_COOKIE.length + 1);
    if (!raw) {
        return null;
    }

    try {
        const stored = JSON.parse(decodeURIComponent(raw));

        return stored.version === config.version ? { analytics: stored.analytics === true, marketing: stored.marketing === true } : null;
    } catch {
        return null;
    }
}

/**
 * Uloží volbu do cookie.
 *
 * @param {{ analytics: boolean, marketing: boolean }} choice
 */
function storeChoice({ analytics, marketing }) {
    const value = encodeURIComponent(JSON.stringify({ version: config.version, analytics, marketing, at: new Date().toISOString() }));
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${CONSENT_COOKIE}=${value}; Max-Age=${config.maxAgeDays * SECONDS_PER_DAY}; Path=/; SameSite=Lax${secure}`;
}

/**
 * Smaže cookies Google Analytics — GA je zakládá na nejvyšší možné doméně
 * (slevohlidka.rhsoft.cz → .rhsoft.cz), proto se maže na všech úrovních adresy.
 */
function deleteAnalyticsCookies() {
    const names = document.cookie
        .split('; ')
        .map((part) => part.split('=')[0])
        .filter((name) => name.startsWith(ANALYTICS_COOKIE_PREFIX));
    const labels = window.location.hostname.split('.');
    const domains = [''];
    for (let i = 0; i < labels.length - 1; i++) {
        domains.push(`; Domain=.${labels.slice(i).join('.')}`);
    }

    for (const name of names) {
        for (const domain of domains) {
            document.cookie = `${name}=; Max-Age=0; Path=/${domain}`;
        }
    }
}

/**
 * Načte gtag.js a spustí měření (jen jednou, jen s ID měření — mimo produkci ho server nepošle).
 */
function loadAnalytics() {
    if (analyticsLoaded || !config.measurementId) {
        return;
    }
    analyticsLoaded = true;

    gtag('js', new Date());
    // Zobrazení stránky posílá measurePageView — výchozí by neslo adresu s tokeny (R69)
    gtag('config', config.measurementId, { send_page_view: false });
    measurePageView();

    const script = document.createElement('script');
    script.async = true;
    script.src = `${GTAG_URL}?id=${encodeURIComponent(config.measurementId)}`;
    document.head.appendChild(script);
}

/**
 * Adresa aktuální stránky pro měření: u stránek s tokenem v adrese (obnova hesla, ověření
 * e-mailu, odhlášení z e-mailů — letaky.cookie_consent.redacted_paths) jen jejich začátek
 * bez zbytku cesty a parametrů.
 *
 * @returns {string}
 */
function pageLocation() {
    const url = new URL(window.location.href);
    const redacted = config.redactedPaths.find((prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`));

    return redacted ? `${url.origin}${redacted}` : url.href;
}

/**
 * Změří zobrazení aktuální stránky s očištěnou adresou. Adresu nastaví i pro další události
 * GA (čas na stránce), ty by jinak vzaly adresu z prohlížeče.
 */
function measurePageView() {
    const location = pageLocation();
    if (!analyticsLoaded || location === lastMeasuredLocation) {
        return;
    }
    lastMeasuredLocation = location;

    gtag('set', { page_location: location });
    gtag('event', 'page_view', { page_location: location, page_title: document.title });
}

/**
 * Promítne volbu do Consent Mode a podle ní spustí nebo utlumí měření.
 *
 * @param {{ analytics: boolean, marketing: boolean }} choice
 */
function applyChoice(choice) {
    Object.assign(consentState, choice);
    gtag('consent', 'update', consentModeFor(choice));

    if (choice.analytics) {
        loadAnalytics();
    } else {
        deleteAnalyticsCookies();
    }
}

/**
 * Spustí se při startu aplikace: výchozí Consent Mode (vše zakázané) a uložená volba.
 *
 * @param {{ measurementId: string|null, version: number, maxAgeDays: number, redactedPaths: string[] } | undefined} serverConfig
 */
export function initConsent(serverConfig) {
    config = { ...config, ...serverConfig };
    gtag('consent', 'default', consentModeFor({ analytics: false, marketing: false }));

    // Přechod mezi stránkami SPA — titulek nastaví <Head> až po vykreslení nové stránky
    router.on('navigate', () => window.setTimeout(measurePageView));

    const stored = readStoredChoice();
    if (stored) {
        consentState.decided = true;
        applyChoice(stored);
    }
}

/**
 * Uloží volbu uživatele (lišta i nastavení) a zavře je.
 *
 * @param {{ analytics: boolean, marketing: boolean }} choice
 */
export function saveConsent(choice) {
    storeChoice(choice);
    consentState.decided = true;
    consentState.settingsOpen = false;
    applyChoice(choice);
}

/**
 * Otevře nastavení cookies (odkaz v patičce) — souhlas jde kdykoli změnit nebo odvolat.
 */
export function openConsentSettings() {
    consentState.settingsOpen = true;
}

/**
 * Zavře nastavení bez uložení.
 */
export function closeConsentSettings() {
    consentState.settingsOpen = false;
}
