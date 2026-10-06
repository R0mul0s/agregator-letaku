/**
 * Nastaví uložený vzhled (světlý / tmavý) ještě před prvním vykreslením stránky
 * a označí stránku třídou has-js.
 *
 * Načítá se blokujícím <script> v <head> (resources/views/app.blade.php) —
 * modul z Vite by běžel až po vykreslení a stránka by problikla špatným režimem.
 * Klíč a hodnoty musí odpovídat resources/js/lib/theme.js.
 *
 * Třída has-js skryje obsah ze serveru pro roboty (.seo-content, R94) — bez JavaScriptu
 * zůstane vidět, s ním ho po spuštění nahradí aplikace a stránka neproblikne (R96).
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
(function () {
    document.documentElement.classList.add('has-js');

    try {
        var theme = window.localStorage.getItem('letaky-theme');
        if (theme === 'light' || theme === 'dark') {
            document.documentElement.setAttribute('data-theme', theme);
        }
    } catch (error) {
        // Nedostupné úložiště (soukromý režim) — zůstane vzhled podle systému.
    }
})();
