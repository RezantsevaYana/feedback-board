<template>
  <BaseModal :label="ask.title" narrow tag="form" @close="emit('close')" @submit.prevent="save">
    <div class="drawer-top">
      <h2>{{ title }}</h2>
      <button type="button" class="icon-btn" aria-label="Отмена" @click="close()"><AppIcon name="close" /></button>
    </div>
    <p v-if="isBulk" class="note bulk-authors">
      Этот ответ увидят: {{ authorsLabel }}
    </p>
    <TextAreaComponent
      v-model="reply"
      :label="ask.label"
      :hint="hintText"
      :error="error"
      rows="4"
      maxlength="2000"
      :placeholder="ask.placeholder"
      autofocus
    />
    <div class="row">
      <button class="btn btn-primary" type="submit" :disabled="saving" :aria-busy="saving">{{ ask.title }}</button>
      <button class="btn" type="button" @click="close()">Отмена</button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import type { Post, Status, SystemStatusSlug } from '@/api/types'
import { STATUS_REPLY } from '@/shared/constants/dict'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// Asked when one or more cards go to «Готово» (what exactly is done) or «Отклонено» (why).
// Single-card: existing per-post reply. Multiple: one shared reply attached to every selected card,
// with a visible head-up list of the authors so the admin writes something concrete, not «Спасибо».
const props = defineProps<{
  posts: Post[]
  status: Status
}>()
const emit = defineEmits<{
  close: []
}>()
const board = useBoardStore()
const notification = useNotificationStore()
const close = inject<() => void>('baseModal:close', () => emit('close'))

const ask = computed(() => STATUS_REPLY[props.status.slug as SystemStatusSlug]!)
const isBulk = computed(() => props.posts.length > 1)
// the modal opens for one card or a selection, never for none
const first = computed(() => props.posts[0]!)

const title = computed(() => isBulk.value
  ? `${ask.value.title}: ${props.posts.length} карточек`
  : `${ask.value.title}: «${first.value.title}»`)

const uniqueAuthors = computed(() => {
  const names = props.posts.map((p) => p.author_name?.trim() || 'аноним')
  return Array.from(new Set(names))
})
const authorsLabel = computed(() => {
  const head = uniqueAuthors.value.slice(0, 3).join(', ')
  const rest = uniqueAuthors.value.length - 3
  return rest > 0 ? `${head} и ещё ${rest}` : head
})

const hintText = computed(() => {
  const base = ask.value.required ? 'обязательно' : 'можно пропустить'
  return isBulk.value ? `${base}, увидят все выбранные авторы` : `${base}, увидят все на доске`
})

const reply = ref(isBulk.value ? '' : (first.value.team_comment ?? ''))
const error = ref('')
const saving = ref(false)

async function save() {
  error.value = ''
  const trimmed = reply.value.trim()
  if (ask.value.required && !trimmed) {
    error.value = 'Напишите, почему отклонено, — это увидят все.'
    return
  }
  saving.value = true
  try {
    if (isBulk.value) {
      await board.bulkMove(props.status.id, trimmed || null)
      close()
    } else {
      const post = first.value
      const prev = { status_id: post.status.id, team_comment: post.team_comment }
      await board.updatePost(post.id, { status_id: props.status.id, team_comment: trimmed || null })
      notification.success(props.status.slug === 'done' ? 'Готово — видно на доске' : 'Отклонено', {
        label: 'Отменить',
        run: () => board.updatePost(post.id, prev).catch((e) => notification.error(errorMessage(e))),
      })
      close()
    }
  } catch (e) {
    error.value = e instanceof ApiError ? (e.field('team_comment') ?? e.message) : String(e)
  } finally {
    saving.value = false
  }
}
</script>
