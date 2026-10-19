<script setup lang="ts">
import { computed, ref } from 'vue'
import { ApiError } from '@/api/client'
import type { Post, PostStatus } from '@/api/types'
import { STATUS_REPLY } from '@/lib/dict'
import { useToast } from '@/lib/toast'
import { useBoardStore } from '@/stores/board'
import AppIcon from './AppIcon.vue'
import BaseModal from './BaseModal.vue'

// Asked when a card is dragged to «Готово» (what exactly is done — optional) or «Отклонено» (why — required).
// The answer becomes the public «Ответ команды».
const props = defineProps<{ post: Post; status: PostStatus }>()
const emit = defineEmits<{ close: [] }>()
const board = useBoardStore()
const toast = useToast()

const ask = computed(() => STATUS_REPLY[props.status]!)
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
    await board.updatePost(props.post.id, { status: props.status, team_comment: reply.value.trim() || null })
    toast.show(props.status === 'done' ? 'Готово — видно на доске' : 'Отклонено — причина видна на доске')
    emit('close')
  } catch (e) {
    error.value = e instanceof ApiError ? (e.field('team_comment') ?? e.message) : String(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseModal :label="ask.title" narrow tag="form" @close="emit('close')" @submit.prevent="save">
    <div class="drawer-top">
      <h2>{{ ask.title }}: «{{ post.title }}»</h2>
      <button type="button" class="icon-btn" aria-label="Отмена" @click="emit('close')"><AppIcon name="close" /></button>
    </div>
    <label class="field">
      <span>{{ ask.label }} <small>· {{ ask.required ? 'обязательно' : 'можно пропустить' }}, увидят все на доске</small></span>
      <textarea v-model="reply" class="input" :class="{ invalid: error }" rows="4" maxlength="2000" :placeholder="ask.placeholder" autofocus />
      <span v-if="error" class="err" role="alert">{{ error }}</span>
    </label>
    <div class="row">
      <button class="btn btn-primary" type="submit" :disabled="saving">{{ ask.title }}</button>
      <button class="btn" type="button" @click="emit('close')">Отмена</button>
    </div>
  </BaseModal>
</template>