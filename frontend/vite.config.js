import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './src'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: function (id) {
                    if (id.includes('node_modules')) {
                        // نجمع كل ما يتعلق بـ React و scheduler لمنع مشكلة useSyncExternalStore
                        if (id.includes('react') || id.includes('scheduler') || id.includes('use-sync-external-store')) {
                            return 'vendor-react';
                        }
                        if (id.includes('lucide-react') || id.includes('framer-motion') || id.includes('@headlessui')) {
                            return 'vendor-ui';
                        }
                        if (id.includes('axios') || id.includes('i18next')) {
                            return 'vendor-utils';
                        }
                        return 'vendor';
                    }
                }
            }
        }
    },
    server: {
        port: 5173,
        host: true,
        proxy: {
            '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
            },
            '/broadcasting': {
                target: 'http://localhost:8080',
                ws: true,
                changeOrigin: true,
            },
        },
    },
});
