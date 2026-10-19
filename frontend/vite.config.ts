import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// The board talks to Laravel through this proxy, so the API, the Sanctum session cookie and
// widget.js share one origin. In Docker the backend is reachable as http://backend:8000.
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8000'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // CORS for the widget's cross-site submissions is decided by Laravel per project, not by Vite
    cors: false,
    proxy: {
      '/api': apiTarget,
      '/sanctum': apiTarget,
    },
  },
})