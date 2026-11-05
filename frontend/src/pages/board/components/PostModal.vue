<template>
  <BaseModal :label="post?.title ?? 'Предложение'" :tag="board.team ? 'form' : 'article'" @close="emit('close')" @submit.prevent="save()">
    <template v-if="missing">
      <div class="drawer-top">
        <h1>Предложение не найдено</h1>
        <div class="drawer-actions">
          <CopyLink v-if="post" :post-id="post.id" />
          <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
        </div>
      </div>
      <p class="desc">Возможно, его удалили или скрыли.</p>
    </template>

    <!-- loading: the card's own layout in placeholders (tags, title, meta, text, votes) -->
    <div v-else-if="!post" class="sk-stack" role="status">
      <span class="visually-hidden">Загружаем предложение…</span>
      <div class="drawer-top" aria-hidden="true">
        <span class="sk sk-chip" />
        <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
      </div>
      <span class="sk sk-h1" aria-hidden="true" />
      <span class="sk sk-meta" aria-hidden="true" />
      <span class="sk sk-line" aria-hidden="true" />
      <span class="sk sk-line" aria-hidden="true" />
      <span class="sk sk-line sk-short" aria-hidden="true" />
      <span class="sk sk-votes" aria-hidden="true" />
    </div>

    <!-- team: edit form -->
    <template v-else-if="board.team">
      <div class="drawer-top">
        <span class="row">
          <PostTags :post="post" />
          <span class="note"><span class="mono">#{{ post.id }}</span> · {{ fmtDate(post.created_at) }} · {{ post.author_name || TEXT.anonymous }}</span>
        </span>
        <div class="drawer-actions">
          <CopyLink v-if="post" :post-id="post.id" />
          <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
        </div>
      </div>
      <InputComponent v-model="edit.title" label="Заголовок" :error="errors.title" maxlength="120" />
      <TextAreaComponent v-model="edit.description" label="Описание" :error="errors.description" rows="6" maxlength="5000" />
      <div v-if="post.attachments?.length" class="field">
        <span>Скриншоты</span>
        <AttachmentShots :attachments="post.attachments" />
      </div>
      <!-- how the board reacted: read at a glance, apart from the post's own actions -->
      <div class="post-votes">
        <span class="post-votes-label">Голоса</span>
        <VoteButtons :post="post" small />
        <span class="note">{{ voteSummary }}</span>
      </div>
      <div class="grid3">
        <SelectComponent v-model="edit.category_id" label="Категория" :options="categoryOptions" />
        <SelectComponent v-model="edit.status_id" label="Статус" :options="statusOptions" />
        <SelectComponent v-model="edit.priority" label="Приоритет" hint="только команде" :options="priorityOptions" />
      </div>
      <TextAreaComponent
        v-model="edit.team_comment"
        :label="currentReply?.label ?? 'Ответ команды'"
        :hint="`${currentReply?.required ? 'обязательно, ' : ''}виден всем на доске`"
        :error="errors.team_comment"
        rows="3"
        maxlength="2000"
        :placeholder="currentReply?.placeholder ?? 'Например, когда планируем'"
      />
      <div class="row">
        <button class="btn btn-primary" type="submit" :disabled="!!saving" :aria-busy="saving === 'save'">Сохранить</button>
        <button class="btn" type="button" :disabled="!!saving" :aria-busy="saving === 'hide'" @click="save({ is_hidden: !post.is_hidden })">
          {{ post.is_hidden ? 'Показать на доске' : 'Скрыть с доски' }}
        </button>
        <span class="spacer" />
        <button class="btn btn-danger" type="button" @click="askDelete">Удалить</button>
      </div>
    </template>

    <!-- guest: read-only card -->
    <template v-else>
      <div class="drawer-top">
        <PostTags :post="post" status />
        <div class="drawer-actions">
          <CopyLink v-if="post" :post-id="post.id" />
          <button type="button" class="icon-btn" aria-label="Закрыть" @click="close()"><AppIcon name="close" /></button>
        </div>
      </div>
      <h1>{{ post.title }}</h1>
      <div class="meta">
        <span>{{ post.author_name || TEXT.anonymous }}</span>
        <span>Создано {{ fmtDate(post.created_at) }}</span>
        <span v-if="post.status.slug !== 'new'">{{ post.status.name }} {{ fmtDate(post.status_changed_at) }}</span>
        <span class="mono">#{{ post.id }}</span>
      </div>
      <p class="desc">{{ post.description }}</p>
      <AttachmentShots v-if="post.attachments" :attachments="post.attachments" />
      <div v-if="post.team_comment" class="answer" :data-s="post.status.slug">
        <b>{{ post.status.slug === 'done' ? 'Что сделано' : post.status.slug === 'rejected' ? 'Почему отклонено' : 'Ответ команды' }}</b>
        <p>{{ post.team_comment }}</p>
      </div>
      <VoteButtons :post="post" />
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, inject, reactive, ref, watch } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import { PostsApi } from '@/api/posts'
import type { Post, PostPriority, SystemStatusSlug } from '@/api/types'
import { NO_CATEGORY, PRIORITIES, PRIORITY, STATUS_REPLY, TEXT } from '@/shared/constants/dict'
import { fmtDate } from '@/shared/utils/format'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import AttachmentShots from './AttachmentShots.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import CopyLink from './CopyLink.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'
import PostTags from './PostTags.vue'
import VoteButtons from './VoteButtons.vue'

// Post card at /<slug>/posts/<id>: read-only for guests, an edit form in team mode.
const props = defineProps<{
  id: number
}>()
const emit = defineEmits<{
  close: []
}>()
const board = useBoardStore()
const notification = useNotificationStore()
const confirm = useConfirmStore()
const close = inject<() => void>('baseModal:close', () => emit('close'))

const post = ref<Post | null>(null)
// «рейтинг +3» next to the team's vote buttons: likes minus dislikes, as the board sorts by it
const voteSummary = computed(() => {
  if (!post.value) return ''
  const n = post.value.likes_count - post.value.dislikes_count
  if (!post.value.likes_count && !post.value.dislikes_count) return 'пока никто не голосовал'
  return `рейтинг ${n > 0 ? '+' : ''}${n}`
})

const missing = ref(false)
// which button is working: the spinner goes on that one, both are locked
const saving = ref<'save' | 'hide' | null>(null)
const errors = reactive<Record<string, string>>({})
const edit = reactive({
  title: '',
  description: '',
  category_id: null as number | null,
  status_id: 0,
  priority: '' as '' | PostPriority,
  team_comment: '',
})

const categoryOptions = computed(() => [
  { value: null, label: NO_CATEGORY },
  ...board.categories.map((c) => ({ value: c.id, label: c.name })),
])
const statusOptions = computed(() => board.statuses.map((s) => ({ value: s.id, label: s.name })))
const priorityOptions = [
  { value: '' as const, label: 'Без приоритета' },
  ...PRIORITIES.map((p) => ({ value: p, label: PRIORITY[p] })),
]

// Выбранный в селекте статус — для подсказки «Ответ команды» (для кастомных ничего не меняется)
const currentReply = computed(() => {
  const slug = board.statuses.find((s) => s.id === edit.status_id)?.slug
  return slug ? STATUS_REPLY[slug as SystemStatusSlug] : undefined
})

async function load() {
  missing.value = false
  post.value = null
  try {
    const res = board.team ? await PostsApi.adminGet(props.id) : await PostsApi.get(board.project?.slug ?? '', props.id)
    // reuse the board's copy when there is one, so votes stay in sync in both places
    post.value = Object.assign(board.posts.find((p) => p.id === res.data.id) ?? res.data, res.data)
    Object.assign(edit, {
      title: res.data.title,
      description: res.data.description,
      category_id: res.data.category?.id ?? null,
      status_id: res.data.status.id,
      priority: res.data.priority ?? '',
      team_comment: res.data.team_comment ?? '',
    })
  } catch (e) {
    if (e instanceof ApiError && (e.status === 404 || e.status === 403)) missing.value = true
    else notification.error(errorMessage(e))
  }
}
watch(() => [props.id, board.team], load, { immediate: true })

async function save(patch?: { is_hidden: boolean }) {
  if (!post.value) return
  for (const key of Object.keys(errors)) delete errors[key]
  saving.value = patch ? 'hide' : 'save'
  try {
    const body = patch ?? { ...edit, priority: edit.priority || null, team_comment: edit.team_comment.trim() || null }
    Object.assign(post.value, await board.updatePost(post.value.id, body))
    if (patch) notification.success(post.value.is_hidden ? 'Скрыто с доски' : 'Снова на доске')
    else {
      notification.success('Сохранено')
      close()
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [key, messages] of Object.entries(e.errors)) if (messages[0]) errors[key] = messages[0]
    } else notification.error(errorMessage(e))
  } finally {
    saving.value = null
  }
}

async function askDelete() {
  if (!post.value) return
  const ok = await confirm.ask({
    title: 'Удалить предложение?',
    message: `«${post.value.title}» — удалить навсегда вместе со скриншотами и голосами. Отменить нельзя.`,
    confirmText: 'Удалить',
    danger: true,
  })
  if (!ok) return
  try {
    await board.deletePost(post.value.id)
    notification.success('Предложение удалено')
    close()
  } catch (e) {
    notification.error(errorMessage(e))
  }
}
</script>
