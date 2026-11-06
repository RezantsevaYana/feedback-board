<template>
  <div class="board-filters" :class="{ stacked }">
    <div :class="{ field: stacked }">
      <span v-if="stacked">Категория</span>
      <CategoryChips />
    </div>
    <MultiSelectComponent
      v-if="board.view.layout === 'list'"
      v-model="board.view.status"
      :label="stacked ? 'Статус' : undefined"
      :options="statusOptions"
      all-label="Все статусы"
      :count-label="(n) => `${n} ${plural(n, 'статус', 'статуса', 'статусов')}`"
      aria-label="Статус"
    />
    <template v-if="board.team">
      <MultiSelectComponent
        v-model="board.view.priority"
        :label="stacked ? 'Приоритет' : undefined"
        :options="priorityOptions"
        all-label="Любой приоритет"
        :count-label="(n) => `${n} ${plural(n, 'приоритет', 'приоритета', 'приоритетов')}`"
        aria-label="Приоритет"
      />
      <CheckboxComponent v-model="board.view.showHidden" label="Показывать скрытые" />
    </template>
    <span v-if="!stacked" class="spacer" />
    <SelectComponent v-model="board.view.sort" :label="stacked ? 'Сортировка' : undefined" :options="sortOptions" aria-label="Сортировка" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { PRIORITIES, PRIORITY } from '@/shared/constants/dict'
import { type PriorityFilter, type Sort, useBoardStore } from '@/store/board/useBoardStore'
import { plural } from '@/shared/utils/format'
import CheckboxComponent from '@/shared/ui/CheckboxComponent.vue'
import MultiSelectComponent from '@/shared/ui/MultiSelectComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import CategoryChips from './CategoryChips.vue'

// The board's filters and sort. In the toolbar row on desktop; `stacked` with labels inside FiltersModal on a phone.
defineProps<{
  stacked?: boolean
}>()
const board = useBoardStore()

const statusOptions = computed(() => board.statuses.map((s) => ({ value: s.id, label: s.name })))
const priorityOptions: { value: PriorityFilter; label: string }[] = [
  { value: 'none', label: 'Без приоритета' },
  ...PRIORITIES.map((p) => ({ value: p, label: PRIORITY[p] })),
]
const sortOptions = computed(() => [
  { value: 'new' as Sort, label: 'Сначала новые' },
  { value: 'top' as Sort, label: 'Сначала популярные' },
  ...(board.team ? [{ value: 'priority' as Sort, label: 'По приоритету' }] : []),
])
</script>