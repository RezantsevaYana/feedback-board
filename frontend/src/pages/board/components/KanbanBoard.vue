<template>
  <div class="kanban">
    <section
      v-for="(col, n) in columns"
      :key="col.status.id"
      class="col"
      :class="{ over: overColumn === col.status.id }"
      :aria-label="`${col.status.name}: ${col.posts.length}`"
      @dragover="dragId !== null && ($event.preventDefault(), (overColumn = col.status.id))"
      @dragleave="overColumn === col.status.id && (overColumn = null)"
      @drop.prevent="onDrop(col.status)"
    >
      <div class="col-head">
        <h2 class="pill" :data-color="col.status.color ?? 'none'">{{ col.status.name }}</h2>
        <span class="col-count" :title="board.team && n < 9 ? `Клавиша ${n + 1} переносит карточку сюда` : undefined">{{ col.posts.length }}</span>
      </div>
      <article
        v-for="post in col.posts"
        :key="post.id"
        class="kcard"
        :class="{ 'is-hidden': post.is_hidden, dragging: dragId === post.id }"
        :data-post-id="post.id"
        :draggable="board.team"
        @click="onCardClick($event, post)"
        @dragstart="onDragStart($event, post)"
        @dragend="(dragId = null), (overColumn = null)"
      >
        <div class="kcard-top">
          <PostTags :post="post" :team="board.team" />
          <CardMenu v-if="board.team" :post="post" @move="move(post, $event)" @hide="toggleHidden(post)" />
          <CopyLink v-else :post-id="post.id" />
        </div>
        <div class="card-body">
          <h3><button type="button" class="card-open" @click="open(post)">{{ post.title }}</button></h3>
          <div v-if="post.description" class="excerpt">{{ post.description }}</div>
          <p v-if="post.status.slug !== 'new' && post.team_comment" class="kcard-reply" :data-s="post.status.slug">{{ post.team_comment }}</p>
          <PostMeta :post="post" />
        </div>
        <VoteButtons :post="post" small />
      </article>
      <template v-if="!col.posts.length">
        <!-- the team cleared the queue: a small win instead of an empty tray -->
        <div v-if="board.team && col.status.slug === 'new' && !board.filtered" class="col-empty col-cleared">
          <span class="col-cleared-mark" aria-hidden="true"><AppIcon name="check" :size="18" /></span>
          <b>Всё разобрано</b>
          <span>Новые предложения появятся здесь</span>
        </div>
        <div v-else class="col-empty">{{ board.team ? 'Перетащите карточку сюда' : 'Пока пусто' }}</div>
      </template>
    </section>
  </div>
  <StatusReplyModal v-if="asking" :post="asking.post" :status="asking.status" @close="asking = null" />
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Post, Status } from '@/api/types'
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

// Columns come from the project's statuses (3 системных + добавленные командой).
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

function onDragStart(e: DragEvent, post: Post) {
  dragId.value = post.id
  e.dataTransfer?.setData('text/plain', String(post.id))
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}

function onDrop(status: Status) {
  const post = board.posts.find((p) => p.id === dragId.value)
  dragId.value = null
  overColumn.value = null
  if (post) move(post, status)
}
</script>