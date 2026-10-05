/**
 * Nastaví uložený vzhled (světlý / tmavý) ještě před prvním vykreslením stránky „Brzy spouštíme“.
 *
 * Načítá se blokujícím <script> v <head> — CSP nedovolí inline skript a odložený skript
 * by běžel až po vykreslení (stránka by problikla). Klíč a hodnoty jako v aplikaci
 * (public/theme-init.js, resources/js/lib/theme.js) a v js/main.js.
 *
 * @author Roman Hlaváček
 * @created 2026-10-05
 */
(function () {
    try {
        var theme = window.localStorage.getItem('letaky-theme');
        if (theme === 'light' || theme === 'dark') {
            document.documentElement.setAttribute('data-theme', theme);
        }
    } catch (error) {
        // Nedostupné úložiště (soukromý režim) — zůstane vzhled podle systému.
    }
})();
