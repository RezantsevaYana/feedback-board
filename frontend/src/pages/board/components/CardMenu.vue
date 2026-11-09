<template>
  <MenuComponent :items="items" :aria-label="`Действия: ${post.title}`" title="Действия" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Post, Status } from '@/api/types'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useCopyLink } from '../composables/useCopyLink'

// Team's quick actions on a card, without opening it: move to a column (with its number key), hide, copy the link.
const props = defineProps<{
  post: Post
  reorderable?: boolean
  canRaise?: boolean
  canLower?: boolean
}>()
const emit = defineEmits<{
  move: [status: Status]
  hide: []
  reorder: [direction: 'up' | 'down']
}>()
const board = useBoardStore()
const { copy } = useCopyLink()

const items = computed<MenuItem[]>(() => [
  ...(props.reorderable ? [
    { label: 'Выше в колонке', disabled: !props.canRaise, run: () => emit('reorder', 'up') },
    { label: 'Ниже в колонке', disabled: !props.canLower, run: () => emit('reorder', 'down') },
  ] : []),
  ...board.statuses.map((s, i) => ({
    label: `В «${s.name}»`,
    key: i < 9 ? String(i + 1) : undefined,
    disabled: s.id === props.post.status.id,
    run: () => emit('move', s),
  })),
  { label: props.post.is_hidden ? 'Показать на доске' : 'Скрыть с доски', key: 'H', separated: true, run: () => emit('hide') },
  { label: 'Копировать ссылку', run: () => copy(props.post.id) },
])
</script>
