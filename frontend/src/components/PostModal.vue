<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { api, ApiError } from '@/api/client'
import type { Item, Post, PostPriority, PostStatus } from '@/api/types'
import { NO_CATEGORY, PRIORITIES, PRIORITY, STATUS, STATUS_REPLY, STATUSES, TEXT } from '@/lib/dict'
import { fmtDate } from '@/lib/format'
import { useToast } from '@/lib/toast'
import { useBoardStore } from '@/stores/board'
import AppIcon from './AppIcon.vue'
import AttachmentShots from './AttachmentShots.vue'
import BaseModal from './BaseModal.vue'
import PostTags from './PostTags.vue'
import VoteButtons from './VoteButtons.vue'

// Post card at /<slug>/posts/<id>: read-only for guests, an edit form in team mode.
const props = defineProps<{ id: number }>()
const emit = defineEmits<{ close: [] }>()
const board = useBoardStore()
const toast = useToast()

const post = ref<Post | null>(null)
const missing = ref(false)
const saving = ref(false)
const confirmDelete = ref(false)
const errors = reactive<Record<string, string>>({})
const edit = reactive({
  title: '',
  description: '',
  category_id: null as number | null,
  status: 'new' as PostStatus,
  priority: '' as '' | PostPriority,
  team_comment: '',
})

async function load() {
  missing.value = false
  post.value = null
  confirmDelete.value = false
  try {
    const res = await api<Item<Post>>(
      board.team ? `/admin/posts/${props.id}` : `/public/projects/${encodeURIComponent(board.project?.slug ?? '')}/posts/${props.id}`,
    )
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
    else toast.error(e instanceof ApiError ? e.message : String(e))
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
    if (patch) toast.show(post.value.is_hidden ? 'Скрыто с доски' : 'Снова на доске')
    else {
      toast.show('Сохранено')
      emit('close')
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [key, messages] of Object.entries(e.errors)) if (messages[0]) errors[key] = messages[0]
    } else toast.error(e instanceof ApiError ? e.message : String(e))
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!post.value) return
  try {
    await board.deletePost(post.value.id)
    toast.show('Предложение удалено')
    emit('close')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : String(e))
  }
}
</script>

<template>
  <BaseModal :label="post?.title ?? 'Предложение'" :tag="board.team ? 'form' : 'article'" @close="emit('close')" @submit.prevent="save()">
    <template v-if="missing">
      <div class="drawer-top">
        <h1>Предложение не найдено</h1>
        <button type="button" class="icon-btn" aria-label="Закрыть" @click="emit('close')"><AppIcon name="close" /></button>
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
        <button type="button" class="icon-btn" aria-label="Закрыть" @click="emit('close')"><AppIcon name="close" /></button>
      </div>
      <label class="field">
        <span>Заголовок</span>
        <input v-model="edit.title" class="input" :class="{ invalid: errors.title }" maxlength="120" />
        <span v-if="errors.title" class="err">{{ errors.title }}</span>
      </label>
      <label class="field">
        <span>Описание</span>
        <textarea v-model="edit.description" class="input" :class="{ invalid: errors.description }" rows="6" maxlength="5000" />
        <span v-if="errors.description" class="err">{{ errors.description }}</span>
      </label>
      <div v-if="post.attachments?.length" class="field">
        <span>Скриншоты</span>
        <AttachmentShots :attachments="post.attachments" />
      </div>
      <div class="grid3">
        <label class="field">
          <span>Категория</span>
          <select v-model="edit.category_id" class="input" style="width: 100%">
            <option :value="null">{{ NO_CATEGORY }}</option>
            <option v-for="c in board.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label class="field">
          <span>Статус</span>
          <select v-model="edit.status" class="input" style="width: 100%">
            <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS[s] }}</option>
          </select>
        </label>
        <label class="field">
          <span>Приоритет <small>· только команде</small></span>
          <select v-model="edit.priority" class="input" style="width: 100%">
            <option value="">—</option>
            <option v-for="p in PRIORITIES" :key="p" :value="p">{{ PRIORITY[p] }}</option>
          </select>
        </label>
      </div>
      <label class="field">
        <span>
          {{ STATUS_REPLY[edit.status]?.label ?? 'Ответ команды' }}
          <small>· {{ STATUS_REPLY[edit.status]?.required ? 'обязательно, ' : '' }}виден всем на доске</small>
        </span>
        <textarea
          v-model="edit.team_comment"
          class="input"
          :class="{ invalid: errors.team_comment }"
          rows="3"
          maxlength="2000"
          :placeholder="STATUS_REPLY[edit.status]?.placeholder ?? 'Например, когда планируем'"
        />
        <span v-if="errors.team_comment" class="err">{{ errors.team_comment }}</span>
      </label>
      <div class="row">
        <button class="btn btn-primary" type="submit" :disabled="saving">Сохранить</button>
        <button class="btn" type="button" :disabled="saving" @click="save({ is_hidden: !post.is_hidden })">
          {{ post.is_hidden ? 'Показать на доске' : 'Скрыть с доски' }}
        </button>
        <span class="spacer" />
        <VoteButtons :post="post" small />
        <button class="btn btn-danger" type="button" @click="confirmDelete = true">Удалить</button>
      </div>
      <div v-if="confirmDelete" class="confirm">
        <span>Удалить предложение и скриншоты навсегда?</span>
        <span class="spacer" />
        <button class="btn btn-danger" type="button" @click="remove">Удалить</button>
        <button class="btn" type="button" @click="confirmDelete = false">Отмена</button>
      </div>
    </template>

    <!-- guest: read-only card -->
    <template v-else>
      <div class="drawer-top">
        <PostTags :post="post" status />
        <button type="button" class="icon-btn" aria-label="Закрыть" @click="emit('close')"><AppIcon name="close" /></button>
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