<template>
  <BaseModal label="Категории" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Категории</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <CategoriesEditor />
  </BaseModal>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import CategoriesEditor from '@/shared/components/CategoriesEditor.vue'

// Short path to the category settings right from the board; the same editor lives on /app/boards/:slug/settings.
const emit = defineEmits<{
  close: []
}>()
const close = inject<() => void>('baseModal:close', () => emit('close'))
</script>