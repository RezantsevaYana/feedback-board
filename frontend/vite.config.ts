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
      // Админка сервиса на Orchid живёт на бэкенде. Прокси нужен, чтобы /admin и его ассеты
      // шли через наш origin (:5176) и переиспользовали ту же сессионную куку, что API.
      // На проде то же проксирование делает веб-сервер (nginx) — см. README/DEPLOY.
      // xfwd добавляет X-Forwarded-Host/Proto, чтобы Laravel генерил редиректы (/admin/login
      // при 302 для гостя) на localhost:5176, а не на 'backend:8000' из docker DNS.
      '/admin': { target: apiTarget, changeOrigin: true, xfwd: true },
      '/vendor/orchid': { target: apiTarget, changeOrigin: true, xfwd: true },
    },
  },
})