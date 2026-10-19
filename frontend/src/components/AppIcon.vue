<script setup lang="ts">
// Thin stroke icons used across the board (no emoji in the UI).
const PATHS = {
  thumb: 'M7 10v11H3V10h4Zm0 0 4-8a3 3 0 0 1 3 3v4h5.5a2 2 0 0 1 2 2.3l-1.4 8A2 2 0 0 1 18.1 21H7',
  clip: 'M15.5 6.5 8 14a2.2 2.2 0 0 0 3.1 3.1l7.8-7.8a4.4 4.4 0 0 0-6.2-6.2L4.8 11a6.6 6.6 0 0 0 9.4 9.4l5.7-5.7',
  reply: 'M3.5 5h17v10.5h-9L7 19.5v-4H3.5z',
  columns: 'M3 3.5h5v17H3zM9.5 3.5h5v11h-5zM16 3.5h5v14h-5z',
  list: 'M3.5 6h17M3.5 12h17M3.5 18h17',
  chart: 'M3.5 20.5v-7h5v7M9.5 20.5v-15h5v15M15.5 20.5v-10h5v10',
  close: 'M6 6l12 12M18 6 6 18',
  check: 'm5 12 5 5 9-10',
  chat: 'M3 4.5h18v12H10l-5 4v-4H3z',
} as const

defineProps<{ name: keyof typeof PATHS; size?: number }>()
</script>

<template>
  <svg
    class="ico"
    :width="size ?? 16"
    :height="size ?? 16"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path :d="PATHS[name]" />
  </svg>
</template>