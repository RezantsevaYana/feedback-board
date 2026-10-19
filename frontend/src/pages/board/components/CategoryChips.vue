<template>
  <div class="chips" role="group" aria-label="Категория">
    <button type="button" :aria-pressed="board.view.category === ''" @click="board.view.category = ''">Все</button>
    <button v-for="c in board.categories" :key="c.id" type="button" :aria-pressed="board.view.category === c.id" @click="board.view.category = c.id">
      {{ c.name }}
    </button>
    <button v-if="board.team" type="button" :aria-pressed="board.view.category === 'none'" @click="board.view.category = 'none'">{{ NO_CATEGORY }}</button>
  </div>
</template>

<script setup lang="ts">
import { NO_CATEGORY } from '@/shared/constants/dict'
import { useBoardStore } from '@/store/board/useBoardStore'

// Filter by the project's categories (plus posts left without one after a category was deleted).
const board = useBoardStore()
</script>
