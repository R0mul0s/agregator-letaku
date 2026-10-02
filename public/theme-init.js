/**
 * Nastaví uložený vzhled (světlý / tmavý) ještě před prvním vykreslením stránky.
 *
 * Načítá se blokujícím <script> v <head> (resources/views/app.blade.php) —
 * modul z Vite by běžel až po vykreslení a stránka by problikla špatným režimem.
 * Klíč a hodnoty musí odpovídat resources/js/lib/theme.js.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
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
