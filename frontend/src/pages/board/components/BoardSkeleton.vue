<template>
  <div role="status">
    <span class="visually-hidden">Загружаем…</span>

    <div v-if="layout === 'kanban'" class="kanban" aria-hidden="true">
      <section v-for="(cards, i) in columns" :key="i" class="col">
        <div class="col-head"><span class="sk sk-chip" /><span class="sk sk-num" /></div>
        <div v-for="n in cards" :key="n" class="kcard sk-card">
          <span class="sk sk-chip" />
          <span class="sk sk-title" />
          <span class="sk sk-line" />
          <span class="sk sk-line sk-short" />
          <span class="sk sk-meta" />
          <span class="sk sk-votes" />
        </div>
      </section>
    </div>

    <div v-else-if="layout === 'list'" class="list" aria-hidden="true">
      <div v-for="n in 4" :key="n" class="card sk-card">
        <span class="sk sk-votes" />
        <div class="card-body sk-stack">
          <span class="sk sk-chip" />
          <span class="sk sk-title" />
          <span class="sk sk-line" />
          <span class="sk sk-meta" />
        </div>
        <span class="sk sk-chip" />
      </div>
    </div>

    <div v-else class="rank" aria-hidden="true">
      <div v-for="n in 5" :key="n" class="rank-row sk-card">
        <span class="sk sk-rank" />
        <div class="card-body sk-stack">
          <span class="sk sk-title" />
          <span class="sk sk-meta" />
        </div>
        <span class="sk sk-votes" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useBoardStore } from '@/store/board/useBoardStore'

// Placeholder for posts that haven't arrived yet, in the boxes they will fill:
// kanban columns (as many as the project has, cards 3 / 2 / 1), list cards, or ranking rows (Топ, Авторы).
defineProps<{
  layout: 'kanban' | 'list' | 'rows'
}>()
const board = useBoardStore()

const columns = computed(() => {
  const count = board.statuses.length || 3
  return Array.from({ length: count }, (_, i) => Math.max(1, 3 - i))
})
</script>