<template>
  <div class="kanban">
    <section
      v-for="col in columns"
      :key="col.status.id"
      class="col"
      :class="{ over: overColumn === col.status.id }"
      :aria-label="col.status.name"
      @dragover="dragId !== null && ($event.preventDefault(), (overColumn = col.status.id))"
      @dragleave="overColumn === col.status.id && (overColumn = null)"
      @drop.prevent="onDrop(col.status)"
    >
      <div class="col-head">
        <span class="pill" :data-s="col.status.slug">{{ col.status.name }}</span>
        <span>{{ col.posts.length }}</span>
      </div>
      <article
        v-for="post in col.posts"
        :key="post.id"
        class="kcard"
        :class="{ 'is-hidden': post.is_hidden, dragging: dragId === post.id }"
        :draggable="board.team"
        tabindex="0"
        role="link"
        :aria-label="post.title"
        @click="onCardClick($event, post)"
        @keydown.enter.prevent="onCardEnter(post)"
        @dragstart="onDragStart($event, post)"
        @dragend="(dragId = null), (overColumn = null)"
      >
        <div class="kcard-top">
          <PostTags :post="post" :team="board.team" />
          <CopyLink :post-id="post.id" />
        </div>
        <div class="card-body">
          <h4>{{ post.title }}</h4>
          <p v-if="post.status.slug !== 'new' && post.team_comment" class="kcard-reply">{{ post.team_comment }}</p>
          <PostMeta :post="post" />
        </div>
        <VoteButtons :post="post" small />
      </article>
      <div v-if="!col.posts.length" class="col-empty">{{ board.team ? 'Перетащите карточку сюда' : 'Пока пусто' }}</div>
    </section>
  </div>
  <StatusReplyModal v-if="asking" :post="asking.post" :status="asking.status" @close="asking = null" />
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { errorMessage } from '@/api/client'
import type { Post, Status, SystemStatusSlug } from '@/api/types'
import { STATUS_REPLY } from '@/shared/constants/dict'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import CopyLink from './CopyLink.vue'
import PostMeta from './PostMeta.vue'
import PostTags from './PostTags.vue'
import StatusReplyModal from './StatusReplyModal.vue'
import VoteButtons from './VoteButtons.vue'

// Columns come from the project's statuses (3 системных + добавленные командой).
// Перенос в «Готово» / «Отклонено» сперва просит ответ команды — остальные столбцы не трогают диалог.
const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const notification = useNotificationStore()
const { onCardClick, onCardEnter } = useCardOpen(emit)

const columns = computed(() =>
  board.statuses.map((status) => ({
    status,
    posts: board.posts.filter((p) => p.status.id === status.id),
  })),
)

const dragId = ref<number | null>(null)
const overColumn = ref<number | null>(null)
const asking = ref<{ post: Post; status: Status } | null>(null)

function onDragStart(e: DragEvent, post: Post) {
  dragId.value = post.id
  e.dataTransfer?.setData('text/plain', String(post.id))
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

async function onDrop(status: Status) {
  const post = board.posts.find((p) => p.id === dragId.value)
  dragId.value = null
  overColumn.value = null
  if (!post || post.status.id === status.id) return
  if (STATUS_REPLY[status.slug as SystemStatusSlug]) {
    asking.value = { post, status }
    return
  }
  try {
    await board.updatePost(post.id, { status_id: status.id })
  } catch (e) {
    notification.error(errorMessage(e))
  }
}
</script>
