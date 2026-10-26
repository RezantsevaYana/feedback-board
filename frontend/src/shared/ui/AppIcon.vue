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
  copy: 'M9 9h11v12H9zM5 15H3V3h12v2',
  chat: 'M3 4.5h18v12H10l-5 4v-4H3z',
  chevron: 'm6 9 6 6 6-6',
  sun: 'M12 4V2M12 22v-2M4 12H2m20 0h-2M5.6 5.6 4.2 4.2m15.6 15.6-1.4-1.4M5.6 18.4l-1.4 1.4M19.8 4.2l-1.4 1.4M12 7.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Z',
  moon: 'M20 15.3A8 8 0 1 1 8.7 4a6.5 6.5 0 0 0 11.3 11.3Z',
  monitor: 'M3 4.5h18v12H3zM8 20.5h8M12 16.5v4',
  link: 'M10.5 13.5a4 4 0 0 0 5.7 0l3-3a4 4 0 1 0-5.7-5.7l-1.3 1.3M13.5 10.5a4 4 0 0 0-5.7 0l-3 3a4 4 0 1 0 5.7 5.7l1.3-1.3',
  search: 'M20 20l-4.5-4.5M4 11a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0z',
  eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12ZM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z',
  'eye-off': 'M3 3l18 18M10.6 5.1A9.8 9.8 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.8 8.4 2 12 2 12s3.5 7 10 7c1.9 0 3.6-.6 5-1.4M9.9 9.9a3 3 0 0 0 4.2 4.2',
  more: 'M6.5 12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM13.5 12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM20.5 12a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z',
} as const

defineProps<{
  name: keyof typeof PATHS
  size?: number
}>()
</script>
