<template>
  <BaseModal label="Столбцы" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Столбцы</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <StatusesEditor />
  </BaseModal>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import StatusesEditor from '@/shared/components/StatusesEditor.vue'

// Short path to the column settings right from the board; the same editor lives on /app/boards/:slug/settings.
const emit = defineEmits<{
  close: []
}>()
const close = inject<() => void>('baseModal:close', () => emit('close'))
</script>