import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Back office + auth pages (SB Admin 2, Bootstrap 4)
                'resources/assets/css/admin/app.css',
                'resources/assets/js/admin/app.js',
                // Public site (Landing Page / Shop Homepage / Shop Item, Bootstrap 5)
                'resources/assets/css/public/app.css',
                'resources/assets/js/public/app.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        // Always emit images as files so Vite::asset() can resolve them from the manifest.
        assetsInlineLimit: 0,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
