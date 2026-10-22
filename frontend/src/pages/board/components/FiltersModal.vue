<template>
  <BaseModal label="Фильтры" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Фильтры</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <BoardFilters stacked />
    <div class="row filters-modal-actions">
      <button type="button" class="btn btn-primary" @click="close()">{{ showLabel }}</button>
      <button type="button" class="btn" :disabled="!board.filtered && board.view.sort === 'new'" @click="reset">Сбросить</button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import BoardFilters from './BoardFilters.vue'

// Phone: the board's filters in a bottom sheet. They apply as you pick; the button only closes and says how many are shown.
const emit = defineEmits<{
  close: []
  reset: []
}>()
const board = useBoardStore()
const close = inject<() => void>('baseModal:close', () => emit('close'))

const showLabel = computed(() => (board.loading ? 'Показать' : `Показать ${board.posts.length}`))
function reset() {
  emit('reset')
}
</script>