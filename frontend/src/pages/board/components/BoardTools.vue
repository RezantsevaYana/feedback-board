<template>
  <nav class="board-tools" aria-label="Управление доской">
    <button type="button" class="sidebar-link" @click="emit('categories')">Категории</button>
    <button type="button" class="sidebar-link" @click="emit('statuses')">Столбцы</button>
    <RouterLink :to="`/app/boards/${slug}/settings`" class="sidebar-link">Настройки</RouterLink>
    <RouterLink :to="`/app/boards/${slug}/access`" class="sidebar-link">
      Доступ<span v-if="pending" class="count-badge" :aria-label="`запросов на доступ: ${pending}`">{{ pending }}</span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'

// Owner / admin on the board: quick editors in place (categories, columns) and the board's cabinet pages,
// named as in the cabinet's side menu, so it's one set of places from both sides.
defineProps<{
  slug: string
  /** access requests waiting for a decision */
  pending: number
}>()
const emit = defineEmits<{
  categories: []
  statuses: []
}>()
</script>