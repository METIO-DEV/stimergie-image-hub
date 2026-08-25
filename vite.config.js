import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

const appHostPort = process.env.APP_HOST_PORT ?? '8100';
const viteHostPort = process.env.VITE_HOST_PORT ?? '5174';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: `http://localhost:${viteHostPort}`,
        cors: {
            origin: [`http://localhost:${appHostPort}`, `http://127.0.0.1:${appHostPort}`],
        },
        hmr: {
            host: 'localhost',
            clientPort: Number(viteHostPort),
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
});
