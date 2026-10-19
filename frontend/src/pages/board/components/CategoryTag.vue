<template>
  <span v-if="category" class="type slant" :data-tone="tone">{{ category.name }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Category } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'

// Categories get a colour by their position in the project's list, so the same category is always the same colour.
const props = defineProps<{
  category: Category | null
}>()
const board = useBoardStore()
const tone = computed(() => {
  const index = board.categories.findIndex((c) => c.id === props.category?.id)
  return index < 0 ? 'none' : String(index % 6)
})
</script>
