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

    <div v-else-if="!post" class="empty">Загружаем…</div>

    <!-- team: edit form -->
    <template v-else-if="board.team">
      <div class="drawer-top">
        <span class="row">
          <PostTags :post="post" />
          <span class="mono">#{{ post.id }} · {{ fmtDate(post.created_at) }} · {{ post.author_name || TEXT.anonymous }}</span>
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
      <div class="grid3">
        <SelectComponent v-model="edit.category_id" label="Категория" :options="categoryOptions" />
        <SelectComponent v-model="edit.status" label="Статус" :options="statusOptions" />
        <SelectComponent v-model="edit.priority" label="Приоритет" hint="только команде" :options="priorityOptions" />
      </div>
      <TextAreaComponent
        v-model="edit.team_comment"
        :label="STATUS_REPLY[edit.status]?.label ?? 'Ответ команды'"
        :hint="`${STATUS_REPLY[edit.status]?.required ? 'обязательно, ' : ''}виден всем на доске`"
        :error="errors.team_comment"
        rows="3"
        maxlength="2000"
        :placeholder="STATUS_REPLY[edit.status]?.placeholder ?? 'Например, когда планируем'"
      />
      <div class="row">
        <button class="btn btn-primary" type="submit" :disabled="saving">Сохранить</button>
        <button class="btn" type="button" :disabled="saving" @click="save({ is_hidden: !post.is_hidden })">
          {{ post.is_hidden ? 'Показать на доске' : 'Скрыть с доски' }}
        </button>
        <span class="spacer" />
        <VoteButtons :post="post" small />
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
        <span v-if="post.status !== 'new'">{{ STATUS[post.status] }} {{ fmtDate(post.status_changed_at) }}</span>
        <span class="mono">#{{ post.id }}</span>
      </div>
      <p class="desc">{{ post.description }}</p>
      <AttachmentShots v-if="post.attachments" :attachments="post.attachments" />
      <div v-if="post.team_comment" class="answer" :data-s="post.status">
        <b>{{ post.status === 'done' ? 'Что сделано' : post.status === 'rejected' ? 'Почему отклонено' : 'Ответ команды' }}</b>
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
import type { Post, PostPriority, PostStatus } from '@/api/types'
import { NO_CATEGORY, PRIORITIES, PRIORITY, STATUS, STATUS_REPLY, STATUSES, TEXT } from '@/shared/constants/dict'
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
const missing = ref(false)
const saving = ref(false)
const errors = reactive<Record<string, string>>({})
const edit = reactive({
  title: '',
  description: '',
  category_id: null as number | null,
  status: 'new' as PostStatus,
  priority: '' as '' | PostPriority,
  team_comment: '',
})

const categoryOptions = computed(() => [
  { value: null, label: NO_CATEGORY },
  ...board.categories.map((c) => ({ value: c.id, label: c.name })),
])
const statusOptions = STATUSES.map((s) => ({ value: s, label: STATUS[s] }))
const priorityOptions = [
  { value: '' as const, label: '—' },
  ...PRIORITIES.map((p) => ({ value: p, label: PRIORITY[p] })),
]

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
      status: res.data.status,
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
  saving.value = true
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
    saving.value = false
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
