import { defineConfig, loadEnv } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')
    const hmrHost = env.VITE_HMR_HOST || 'localhost'

    return {
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
            hmr: { host: hmrHost },
            ...(hmrHost !== 'localhost' ? { origin: `http://${hmrHost}:5173` } : {}),
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
    }
})
