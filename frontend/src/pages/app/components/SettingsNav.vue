<template>
  <nav class="settings-nav" aria-label="Разделы настроек">
    <a v-for="item in items" :key="item.hash" :href="item.hash" class="settings-nav-item">{{ item.label }}</a>
    <RouterLink :to="`/app/boards/${slug}/embed`" class="settings-nav-item">Как подключить →</RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'

// Anchors to the sections of BoardSettingsPage; "how to connect" is its own page.
defineProps<{
  slug: string
}>()

const items = [
  { hash: '#general', label: 'Общее' },
  { hash: '#categories', label: 'Категории' },
  { hash: '#statuses', label: 'Столбцы' },
  { hash: '#domains', label: 'Домены' },
]
</script>