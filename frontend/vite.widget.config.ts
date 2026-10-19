import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Embeddable widget: one self-contained IIFE file, public/widget.js, served next to the board.
// Styles come from src/scss/widget.scss (imported `?inline` in widget/main.ts) and are put inside
// the widget's Shadow DOM, so they never leak into (or inherit from) the host site.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  publicDir: false,
  build: {
    outDir: 'public',
    emptyOutDir: false,
    lib: {
      entry: fileURLToPath(new URL('./src/widget/main.ts', import.meta.url)),
      name: 'FeedbackBoardWidget',
      formats: ['iife'],
      fileName: () => 'widget.js',
    },
  },
})