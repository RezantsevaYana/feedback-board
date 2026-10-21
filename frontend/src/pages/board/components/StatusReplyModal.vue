<template>
  <BaseModal :label="ask.title" narrow tag="form" @close="emit('close')" @submit.prevent="save">
    <div class="drawer-top">
      <h2>{{ ask.title }}: «{{ post.title }}»</h2>
      <button type="button" class="icon-btn" aria-label="Отмена" @click="close()"><AppIcon name="close" /></button>
    </div>
    <TextAreaComponent
      v-model="reply"
      :label="ask.label"
      :hint="`${ask.required ? 'обязательно' : 'можно пропустить'}, увидят все на доске`"
      :error="error"
      rows="4"
      maxlength="2000"
      :placeholder="ask.placeholder"
      autofocus
    />
    <div class="row">
      <button class="btn btn-primary" type="submit" :disabled="saving">{{ ask.title }}</button>
      <button class="btn" type="button" @click="close()">Отмена</button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { ApiError } from '@/api/client'
import type { Post, Status, SystemStatusSlug } from '@/api/types'
import { STATUS_REPLY } from '@/shared/constants/dict'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// Asked when a card is dragged to «Готово» (what exactly is done) or «Отклонено» (why); both optional.
// The answer becomes the public «Ответ команды». Срабатывает только для системных столбцов.
const props = defineProps<{
  post: Post
  status: Status
}>()
const emit = defineEmits<{
  close: []
}>()
const board = useBoardStore()
const notification = useNotificationStore()
const close = inject<() => void>('baseModal:close', () => emit('close'))

const ask = computed(() => STATUS_REPLY[props.status.slug as SystemStatusSlug]!)
const reply = ref(props.post.team_comment ?? '')
const error = ref('')
const saving = ref(false)

async function save() {
  error.value = ''
  if (ask.value.required && !reply.value.trim()) {
    error.value = 'Напишите, почему отклонено, — это увидят все.'
    return
  }
  saving.value = true
  try {
    await board.updatePost(props.post.id, { status_id: props.status.id, team_comment: reply.value.trim() || null })
    notification.success(props.status.slug === 'done' ? 'Готово — видно на доске' : 'Отклонено')
    close()
  } catch (e) {
    error.value = e instanceof ApiError ? (e.field('team_comment') ?? e.message) : String(e)
  } finally {
    saving.value = false
  }
}
</script>
