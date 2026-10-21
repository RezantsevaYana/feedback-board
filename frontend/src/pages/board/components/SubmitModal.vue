<template>
  <BaseModal label="Новое предложение" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Новое предложение</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
    </div>
    <SubmitForm :project="project" :categories="categories" :title="title" @sent="emit('sent', $event)">
      <template #secondary>
        <button type="button" class="btn" @click="close()">Отмена</button>
      </template>
    </SubmitForm>
  </BaseModal>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import type { Category, Post } from '@/api/types'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import SubmitForm from '@/shared/components/SubmitForm.vue'

// «Предложить» on the board: the same form as in the widget.
defineProps<{
  project: string
  categories: Category[]
  title?: string
}>()
const emit = defineEmits<{
  close: []
  sent: [post: Post]
}>()
const close = inject<() => void>('baseModal:close', () => emit('close'))
</script>
