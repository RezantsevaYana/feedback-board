import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// The board talks to Laravel through this proxy, so the API, the Sanctum session cookie and
// widget.js share one origin. In Docker the backend is reachable as http://backend:8000.
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8000'

// https://vite.dev/config/
const envDir = fileURLToPath(new URL('../', import.meta.url))

export default defineConfig(({ mode }) => {
  // Docker passes the root .env through environment; a host-side Vite reads it here.
  const env = loadEnv(mode, envDir, 'VITE_')
  const host = process.env.VITE_HOST || env.VITE_HOST || 'localhost'

  return {
    envDir,
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
      host: '0.0.0.0',
      port: 5176,
      strictPort: true,
      hmr: { host, clientPort: 5176 },
      // CORS for the widget's cross-site submissions is decided by Laravel per project, not by Vite
      cors: false,
      proxy: {
        '/api': { target: apiTarget, changeOrigin: true, xfwd: true },
        '/sanctum': { target: apiTarget, changeOrigin: true, xfwd: true },
        // Админка сервиса на Orchid живёт на бэкенде. Прокси нужен, чтобы /admin и его ассеты
        // шли через наш origin (:5176) и переиспользовали ту же сессионную куку, что API.
        // На проде то же проксирование делает веб-сервер (nginx) — см. README/DEPLOY.
        // xfwd добавляет X-Forwarded-Host/Proto, чтобы Laravel генерил редиректы (/admin/login
        // при 302 для гостя) на localhost:5176, а не на 'backend:8000' из docker DNS.
        '/admin': { target: apiTarget, changeOrigin: true, xfwd: true },
        '/vendor/orchid': { target: apiTarget, changeOrigin: true, xfwd: true },
      },
    },
  }
})
