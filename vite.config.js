import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: process.env.VITE_DEV_SERVER_URL || 'http://localhost:5175',
        cors: {
            origin: process.env.APP_URL || 'http://localhost:8083',
        },
        hmr: {
            host: 'localhost',
            clientPort: Number(process.env.VITE_FORWARD_PORT || 5175),
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
