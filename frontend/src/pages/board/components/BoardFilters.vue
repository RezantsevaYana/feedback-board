<template>
  <div class="board-filters" :class="{ stacked }">
    <div :class="{ field: stacked }">
      <span v-if="stacked">Категория</span>
      <CategoryChips />
    </div>
    <SelectComponent v-if="board.view.layout === 'list'" v-model="board.view.status" :label="stacked ? 'Столбец' : undefined" :options="statusOptions" aria-label="Столбец" />
    <template v-if="board.team">
      <SelectComponent v-model="board.view.priority" :label="stacked ? 'Приоритет' : undefined" :options="priorityOptions" aria-label="Приоритет" />
      <CheckboxComponent v-model="board.view.showHidden" label="Показывать скрытые" />
    </template>
    <span v-if="!stacked" class="spacer" />
    <SelectComponent v-model="board.view.sort" :label="stacked ? 'Сортировка' : undefined" :options="sortOptions" aria-label="Сортировка" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { PRIORITIES, PRIORITY } from '@/shared/constants/dict'
import { type Sort, useBoardStore } from '@/store/board/useBoardStore'
import CheckboxComponent from '@/shared/ui/CheckboxComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import CategoryChips from './CategoryChips.vue'

// The board's filters and sort. In the toolbar row on desktop; `stacked` with labels inside FiltersModal on a phone.
defineProps<{
  stacked?: boolean
}>()
const board = useBoardStore()

const statusOptions = computed(() => [
  { value: '' as const, label: 'Все столбцы' },
  ...board.statuses.map((s) => ({ value: s.id, label: s.name })),
])
const priorityOptions = [
  { value: '' as const, label: 'Любой приоритет' },
  { value: 'none' as const, label: 'Без приоритета' },
  ...PRIORITIES.map((p) => ({ value: p, label: PRIORITY[p] })),
]
const sortOptions = computed(() => [
  { value: 'new' as Sort, label: 'Сначала новые' },
  { value: 'top' as Sort, label: 'Сначала популярные' },
  ...(board.team ? [{ value: 'priority' as Sort, label: 'По приоритету' }] : []),
])
</script>