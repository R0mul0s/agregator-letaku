/**
 * Konfigurace Vite — Vue 3 + SCSS.
 *
 * Dev server běží v kontejneru `app` a je z hostitele dostupný na portu 54722
 * (viz compose.yaml), proto poslouchá na všech rozhraních.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

const DEV_SERVER_PORT = 5173;
const DEV_SERVER_PUBLIC_PORT = 54722;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: DEV_SERVER_PORT,
        strictPort: true,
        origin: `http://localhost:${DEV_SERVER_PUBLIC_PORT}`,
        cors: true,
        hmr: {
            host: 'localhost',
            clientPort: DEV_SERVER_PUBLIC_PORT,
        },
        watch: {
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
});
