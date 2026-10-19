<template>
  <div class="kanban">
    <section
      v-for="col in columns"
      :key="col.status"
      class="col"
      :class="{ over: overColumn === col.status }"
      :aria-label="STATUS[col.status]"
      @dragover="dragId !== null && ($event.preventDefault(), (overColumn = col.status))"
      @dragleave="overColumn === col.status && (overColumn = null)"
      @drop.prevent="onDrop(col.status)"
    >
      <div class="col-head">
        <span class="pill" :data-s="col.status">{{ STATUS[col.status] }}</span>
        <span>{{ col.posts.length }}</span>
      </div>
      <article
        v-for="post in col.posts"
        :key="post.id"
        class="kcard"
        :class="{ 'is-hidden': post.is_hidden, dragging: dragId === post.id }"
        :draggable="board.team"
        @dragstart="onDragStart($event, post)"
        @dragend="(dragId = null), (overColumn = null)"
      >
        <PostTags :post="post" :team="board.team" />
        <div class="card-body" role="link" tabindex="0" @click="emit('open', post)" @keydown.enter="emit('open', post)">
          <h4>{{ post.title }}</h4>
          <p v-if="post.status !== 'new' && post.team_comment" class="kcard-reply">{{ post.team_comment }}</p>
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
import type { Post, PostStatus } from '@/api/types'
import { STATUS, STATUS_REPLY, STATUSES } from '@/shared/constants/dict'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import PostMeta from './PostMeta.vue'
import PostTags from './PostTags.vue'
import StatusReplyModal from './StatusReplyModal.vue'
import VoteButtons from './VoteButtons.vue'

// Columns «Новое → Готово / Отклонено». In team mode cards are dragged between columns;
// «Готово» and «Отклонено» first ask for the team's reply.
const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const notification = useNotificationStore()

const columns = computed(() => STATUSES.map((status) => ({ status, posts: board.posts.filter((p) => p.status === status) })))

const dragId = ref<number | null>(null)
const overColumn = ref<PostStatus | null>(null)
const asking = ref<{ post: Post; status: PostStatus } | null>(null)

function onDragStart(e: DragEvent, post: Post) {
  dragId.value = post.id
  e.dataTransfer?.setData('text/plain', String(post.id))
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

async function onDrop(status: PostStatus) {
  const post = board.posts.find((p) => p.id === dragId.value)
  dragId.value = null
  overColumn.value = null
  if (!post || post.status === status) return
  if (STATUS_REPLY[status]) {
    asking.value = { post, status }
    return
  }
  try {
    await board.updatePost(post.id, { status })
  } catch (e) {
    notification.error(errorMessage(e))
  }
}
</script>
