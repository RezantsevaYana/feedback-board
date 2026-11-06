<template>
  <div class="chips" role="group" aria-label="Категория">
    <button type="button" :aria-pressed="!board.view.category.length" @click="board.view.category = []">Все</button>
    <button v-for="c in board.categories" :key="c.id" type="button" :aria-pressed="board.view.category.includes(c.id)" @click="toggle(c.id)">
      {{ c.name }}
    </button>
    <button v-if="board.team" type="button" :aria-pressed="board.view.category.includes('none')" @click="toggle('none')">{{ NO_CATEGORY }}</button>
  </div>
</template>

<script setup lang="ts">
import { NO_CATEGORY } from '@/shared/constants/dict'
import { type CategoryFilter, useBoardStore } from '@/store/board/useBoardStore'

// Filter by the project's categories (plus posts left without one after a category was deleted).
// Chips toggle: several can be on at once and the board shows posts of any of them; «Все» clears the pick.
const board = useBoardStore()

function toggle(value: CategoryFilter) {
  const picked = board.view.category
  board.view.category = picked.includes(value) ? picked.filter((c) => c !== value) : [...picked, value]
}
</script>
