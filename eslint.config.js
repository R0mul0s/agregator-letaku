/**
 * ESLint pro frontend (resources/js) — doporučená pravidla JavaScriptu a Vue 3 (R113).
 * Formátování hlídá ručně psaný styl projektu, ne ESLint; tady jsou jen chyby a podezřelé
 * konstrukce (nepoužité proměnné, nedefinované názvy, chyby šablon Vue).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */
import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}', 'resources/pwa/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
        },
        rules: {
            // Stránky Inertie se jmenují podle obsahu (Home, Offers) a název komponenty v routě je kontrakt se serverem
            'vue/multi-word-component-names': 'off',
            // Rozvržení šablon drží styl projektu (víc atributů na řádku, vlastní zalamování)
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/multiline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-indent': 'off',
            'vue/html-closing-bracket-newline': 'off',
            'vue/first-attribute-linebreak': 'off',
        },
    },
    {
        // Service worker běží v kontextu workeru, ne okna
        files: ['resources/pwa/**/*.js'],
        languageOptions: { globals: { ...globals.serviceworker } },
    },
    {
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'deploy/**'],
    },
];
