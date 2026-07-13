import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: ['resources/views/**', 'routes/**'],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss({
            source: './resources',
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        https: false,
        origin: 'http://192.168.4.140:5173',
        hmr: { host: '192.168.4.140' },
        fs: {
            allow: ['..'],
        },
        watch: {
            usePolling: false,
            ignored: [
                '**/node_modules/**',
                '**/storage/**',
                '**/public/build/**',
                '**/.git/**',
            ],
        },
    },
})
