<script setup lang="ts">
import type { Category, Post } from '@/api/types'
import AppIcon from './AppIcon.vue'
import BaseModal from './BaseModal.vue'
import SubmitForm from './SubmitForm.vue'

// «Предложить» on the board: the same form as in the widget.
defineProps<{ project: string; categories: Category[] }>()
const emit = defineEmits<{ close: []; sent: [post: Post] }>()
</script>

<template>
  <BaseModal label="Новое предложение" narrow @close="emit('close')">
    <div class="drawer-top">
      <h2>Новое предложение</h2>
      <button type="button" class="icon-btn" aria-label="Закрыть" @click="emit('close')"><AppIcon name="close" /></button>
    </div>
    <SubmitForm :project="project" :categories="categories" @sent="emit('sent', $event)">
      <template #secondary>
        <button type="button" class="btn" @click="emit('close')">Отмена</button>
      </template>
    </SubmitForm>
  </BaseModal>
</template>