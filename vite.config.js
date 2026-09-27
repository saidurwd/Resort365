import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/scss/print.scss', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Source Sans 3', {
                    weights: [300, 400, 600, 700],
                    styles: ['normal', 'italic'],
                }),
            ],
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5 and AdminLTE 4 still use @import and global Sass functions.
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
