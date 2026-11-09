<template>
  <div class="kanban">
    <section
      v-for="(col, n) in columns"
      :key="col.status.id"
      class="col"
      :class="{ over: overColumn === col.status.id }"
      :aria-label="`${col.status.name}: ${col.posts.length}`"
      @dragover="onColumnDragOver($event, col.status)"
      @dragleave="overColumn === col.status.id && (overColumn = null)"
      @drop.prevent="onDrop(col.status)"
    >
      <div class="col-head">
        <h2 class="pill" :data-color="col.status.color ?? 'none'" :style="entityColorStyle(col.status.color)">{{ col.status.name }}</h2>
        <span class="col-count" :title="board.team && n < 9 ? `Клавиша ${n + 1} переносит карточку сюда` : undefined">{{ col.posts.length }}</span>
      </div>
      <article
        v-for="post in col.posts"
        :key="post.id"
        class="kcard"
        :class="{ 'is-hidden': post.is_hidden, dragging: dragId === post.id, 'drop-before': dropTarget === post.id && !insertAfter, 'drop-after': dropTarget === post.id && insertAfter }"
        :data-post-id="post.id"
        :draggable="board.team && !savingOrder"
        @click="onCardClick($event, post)"
        @dragstart="onDragStart($event, post)"
        @dragover.stop="onCardDragOver($event, post)"
        @dragend="resetDrag"
      >
        <div class="kcard-top">
          <PostTags :post="post" :team="board.team" />
          <CardMenu v-if="board.team" :post="post" reorderable :can-raise="!savingOrder && col.posts[0]?.id !== post.id" :can-lower="!savingOrder && col.posts.at(-1)?.id !== post.id" @reorder="step(post, $event)" @move="move(post, $event)" @hide="toggleHidden(post)" />
          <CopyLink v-else :post-id="post.id" />
        </div>
        <div class="card-body">
          <h3><button type="button" class="card-open" @click="open(post)">{{ post.title }}</button></h3>
          <div v-if="post.description" class="excerpt">{{ post.description }}</div>
          <p v-if="post.team_comment" class="kcard-reply" :data-s="post.status.slug">{{ post.team_comment }}</p>
        </div>
        <!-- who, when and the votes share one ruled line at the bottom -->
        <div class="kcard-foot" :class="{ liked: post.likes_count > 0 }">
          <PostMeta :post="post" />
          <VoteButtons :post="post" small />
        </div>
      </article>
      <template v-if="!col.posts.length">
        <!-- the team cleared the queue: a small win instead of an empty tray -->
        <div v-if="board.team && col.status.id === board.defaultStatus?.id && !board.filtered" class="col-empty col-cleared">
          <span class="col-cleared-mark" aria-hidden="true"><AppIcon name="check" :size="18" /></span>
          <b>Всё разобрано</b>
          <span>Новые предложения появятся здесь</span>
        </div>
        <div v-else class="col-empty">{{ board.team ? 'Перетащите карточку сюда' : 'Пока пусто' }}</div>
      </template>
    </section>
  </div>
  <StatusReplyModal v-if="asking" :posts="asking.posts" :status="asking.status" @close="asking = null" />
</template>

<script setup lang="ts">
import { entityColorStyle } from '@/shared/utils/entityColor'
import { computed, ref } from 'vue'
import type { Post, Status } from '@/api/types'
import { PostsApi } from '@/api/posts'
import { errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import { useTriage } from '../composables/useTriage'
import { useTriageKeys } from '../composables/useTriageKeys'
import AppIcon from '@/shared/ui/AppIcon.vue'
import CardMenu from './CardMenu.vue'
import CopyLink from './CopyLink.vue'
import PostMeta from './PostMeta.vue'
import PostTags from './PostTags.vue'
import StatusReplyModal from './StatusReplyModal.vue'
import VoteButtons from './VoteButtons.vue'

// Columns follow the board's configured status order.
// The team moves cards by drag, by the card menu or by keys 1–9; «Готово» / «Отклонено» ask for a reply first.
const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const { open, onCardClick } = useCardOpen(emit)
const { asking, move, toggleHidden } = useTriage()
useTriageKeys({ move, toggleHidden })

const columns = computed(() =>
  board.statuses.map((status) => ({
    status,
    posts: board.posts.filter((p) => p.status.id === status.id),
  })),
)

const dragId = ref<number | null>(null)
const overColumn = ref<number | null>(null)
const dropTarget = ref<number | null>(null)
const insertAfter = ref(false)
const savingOrder = ref(false)
const notification = useNotificationStore()

function resetDrag() {
  dragId.value = null
  overColumn.value = null
  dropTarget.value = null
}

function onColumnDragOver(e: DragEvent, status: Status) {
  if (dragId.value === null) return
  e.preventDefault()
  overColumn.value = status.id
  dropTarget.value = null
}

function onCardDragOver(e: DragEvent, post: Post) {
  if (dragId.value === null) return
  e.preventDefault()
  overColumn.value = post.status.id
  dropTarget.value = post.id
  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  insertAfter.value = e.clientY > rect.top + rect.height / 2
}

async function reorder(post: Post, beforeId: number | null) {
  if (savingOrder.value) return
  savingOrder.value = true
  try {
    await PostsApi.reorder(post.id, post.status.id, beforeId)
    board.view.sort = 'manual'
    await board.loadPosts(false, true)
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    savingOrder.value = false
  }
}

function step(post: Post, direction: 'up' | 'down') {
  const posts = columns.value.find(c => c.status.id === post.status.id)?.posts ?? []
  const index = posts.findIndex(p => p.id === post.id)
  const beforeId = direction === 'up' ? posts[index - 1]?.id : posts[index + 2]?.id
  void reorder(post, beforeId ?? null)
}

function onDragStart(e: DragEvent, post: Post) {
  if (!board.team || savingOrder.value) {
    e.preventDefault()
    return
  }
  dragId.value = post.id
  e.dataTransfer?.setData('text/plain', String(post.id))
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

function onDrop(status: Status) {
  const post = board.posts.find((p) => p.id === dragId.value)
  if (post?.id === dropTarget.value) {
    resetDrag()
    return
  }
  const posts = columns.value.find(c => c.status.id === status.id)?.posts.filter(p => p.id !== post?.id) ?? []
  const targetIndex = posts.findIndex(p => p.id === dropTarget.value)
  const beforeId = targetIndex < 0 ? null : posts[targetIndex + (insertAfter.value ? 1 : 0)]?.id ?? null
  resetDrag()
  if (!post) return
  if (post.status.id === status.id) void reorder(post, beforeId)
  else void move(post, status)
}
</script>
