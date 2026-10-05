/**
 * Chování stránky „Brzy spouštíme“ (R79): přepínač vzhledu, střídání cenovky u košíku,
 * ukazatel postupu příprav, nepovinný odpočet do spuštění a letopočet v patičce.
 *
 * Stránka funguje i bez JavaScriptu — prvky, které skript potřebují, mají v HTML `hidden`
 * a skript je ukáže.
 *
 * @author Roman Hlaváček
 * @created 2026-10-05
 */
(function () {
    'use strict';

    /**
     * Začátek provozu na slevohlidka.cz pro odpočet, např. '2026-10-19T08:00:00+02:00'.
     * null = odpočet se neukáže (datum zatím není pevné).
     */
    var LAUNCH_AT = null;

    /** Klíč uloženého vzhledu — stejný jako js/theme-init.js a aplikace. */
    var THEME_STORAGE_KEY = 'letaky-theme';

    /** Texty, které se střídají na cenovce u košíku. */
    var STICKER_TEXTS = ['−50 %', '−30 %', '1+1', '−25 %', '−40 %'];

    /** Jak dlouho zůstane jeden text na cenovce. */
    var STICKER_INTERVAL_MS = 2800;

    /** Délka zmizení cenovky při výměně textu — sedí s --duration-sticker ve style.css. */
    var STICKER_FADE_MS = 300;

    /** Jak často přepočítat odpočet (ukazuje minuty, častěji není potřeba). */
    var COUNTDOWN_REFRESH_MS = 30000;

    var MS_PER_MINUTE = 60000;
    var MINUTES_PER_HOUR = 60;
    var HOURS_PER_DAY = 24;
    var PERCENT = 100;

    /** České tvary slov po číslovce: [1, 2–4, 0 a 5+]. */
    var UNIT_FORMS = {
        days: ['den', 'dny', 'dní'],
        hours: ['hodina', 'hodiny', 'hodin'],
        minutes: ['minuta', 'minuty', 'minut'],
    };

    /**
     * Vrátí, jestli si uživatel v systému vypnul animace.
     *
     * @returns {boolean}
     */
    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * Vrátí vzhled, který stránka právě má — uložená volba, jinak podle systému.
     *
     * @returns {'light'|'dark'}
     */
    function currentTheme() {
        var chosen = document.documentElement.getAttribute('data-theme');
        if (chosen === 'light' || chosen === 'dark') {
            return chosen;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    /**
     * Zapne přepínač světlého a tmavého vzhledu v hlavičce; volbu uloží do localStorage.
     */
    function initThemeToggle() {
        var button = document.querySelector('[data-theme-toggle]');
        if (!button) {
            return;
        }

        var label = button.querySelector('[data-theme-label]');

        // Ikona a popisek ukazují režim, na který tlačítko přepne
        function render() {
            var theme = currentTheme();
            button.setAttribute('data-current', theme);
            var text = theme === 'dark' ? 'Přepnout na světlý vzhled' : 'Přepnout na tmavý vzhled';
            button.setAttribute('title', text);
            if (label) {
                label.textContent = text;
            }
        }

        button.addEventListener('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try {
                window.localStorage.setItem(THEME_STORAGE_KEY, next);
            } catch (error) {
                // Bez úložiště platí volba jen do zavření stránky.
            }
            render();
        });

        // Bez uložené volby sleduje přepínač změnu režimu v systému
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', render);

        render();
        button.hidden = false;
    }

    /**
     * Střídá texty na cenovce u košíku (při vypnutých animacích zůstane první).
     */
    function initSticker() {
        var sticker = document.querySelector('[data-sticker]');
        if (!sticker || prefersReducedMotion()) {
            return;
        }

        var index = 0;
        window.setInterval(function () {
            index = (index + 1) % STICKER_TEXTS.length;
            sticker.classList.add('is-changing');
            window.setTimeout(function () {
                sticker.textContent = STICKER_TEXTS[index];
                sticker.classList.remove('is-changing');
            }, STICKER_FADE_MS);
        }, STICKER_INTERVAL_MS);
    }

    /**
     * Spočítá podíl hotových položek seznamu příprav a naplní ukazatel postupu.
     */
    function initProgress() {
        var list = document.querySelector('[data-checklist]');
        var bar = document.querySelector('[data-progress-bar]');
        var fill = document.querySelector('[data-progress-fill]');
        var percent = document.querySelector('[data-progress-percent]');
        if (!list || !bar || !fill || !percent) {
            return;
        }

        var total = list.children.length;
        var done = list.querySelectorAll('.checklist__item--done').length;
        if (total === 0) {
            return;
        }

        var share = done / total;
        percent.textContent = Math.round(share * PERCENT) + ' %';
        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-label', 'Hotovo');
        bar.setAttribute('aria-valuemin', '0');
        bar.setAttribute('aria-valuemax', String(total));
        bar.setAttribute('aria-valuenow', String(done));
        bar.hidden = false;
        percent.hidden = false;

        // Až po vykreslení prázdného pruhu, ať se plnění animuje
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                fill.style.setProperty('--progress', String(share));
            });
        });
    }

    /**
     * Vrátí tvar slova po číslovce (1 den, 2 dny, 5 dní).
     *
     * @param {number} count
     * @param {string[]} forms [1, 2–4, 0 a 5+]
     * @returns {string}
     */
    function plural(count, forms) {
        if (count === 1) {
            return forms[0];
        }

        return count >= 2 && count <= 4 ? forms[1] : forms[2];
    }

    /**
     * Ukáže odpočet do spuštění, je-li vyplněné LAUNCH_AT; po spuštění ho zase skryje.
     */
    function initCountdown() {
        var box = document.querySelector('[data-countdown]');
        var launchAt = LAUNCH_AT ? new Date(LAUNCH_AT).getTime() : NaN;
        if (!box || isNaN(launchAt)) {
            return;
        }

        var timer = null;

        function render() {
            var minutesLeft = Math.ceil((launchAt - Date.now()) / MS_PER_MINUTE);
            if (minutesLeft <= 0) {
                box.hidden = true;
                window.clearInterval(timer);
                return;
            }

            var values = {
                days: Math.floor(minutesLeft / (MINUTES_PER_HOUR * HOURS_PER_DAY)),
                hours: Math.floor(minutesLeft / MINUTES_PER_HOUR) % HOURS_PER_DAY,
                minutes: minutesLeft % MINUTES_PER_HOUR,
            };

            Object.keys(values).forEach(function (unit) {
                var value = box.querySelector('[data-countdown-unit="' + unit + '"]');
                var name = box.querySelector('[data-countdown-name="' + unit + '"]');
                if (value && name) {
                    value.textContent = String(values[unit]);
                    name.textContent = plural(values[unit], UNIT_FORMS[unit]);
                }
            });
            box.hidden = false;
        }

        render();
        timer = window.setInterval(render, COUNTDOWN_REFRESH_MS);
    }

    /**
     * Doplní do patičky aktuální rok (HTML má rok vydání stránky).
     */
    function initYear() {
        var year = document.querySelector('[data-year]');
        if (year) {
            year.textContent = String(new Date().getFullYear());
        }
    }

    initThemeToggle();
    initSticker();
    initProgress();
    initCountdown();
    initYear();
})();
