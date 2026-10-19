import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Embeddable widget: one self-contained IIFE file, public/widget.js, served next to the board.
// Every SFC is compiled in custom-element mode so its styles land inside the widget's Shadow DOM
// and never leak into (or inherit from) the host site.
export default defineConfig({
  plugins: [vue({ features: { customElement: true } })],
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