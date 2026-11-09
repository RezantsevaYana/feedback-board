<template>
  <nav class="legal-links" aria-label="Правовая информация">
    <a :href="`${base}/privacy`" target="_blank" rel="noopener">Политика обработки данных</a>
    <a :href="`${base}/terms`" target="_blank" rel="noopener">Пользовательское соглашение</a>
    <a :href="`${base}/personal-data-consent`" target="_blank" rel="noopener">Согласие на обработку данных</a>
  </nav>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ origin?: string }>()
const base = computed(() => (props.origin ?? window.location.origin).replace(/\/$/, ''))
</script>
