<template>
  <div v-if="board.team" class="list-top">
    <button
      type="button"
      class="link-btn"
      :aria-pressed="board.selectionMode"
      @click="board.toggleSelectionMode"
    >
      {{ board.selectionMode ? 'Выйти из выбора' : 'Выбрать карточки' }}
    </button>
    <button
      v-if="board.selectionMode && board.hasSelection"
      type="button"
      class="link-btn"
      @click="board.clearSelection"
    >
      Снять выделение
    </button>
  </div>
  <div class="list" :class="{ 'has-check': board.selectionMode }">
    <article
      v-for="post in board.posts"
      :key="post.id"
      class="card"
      :class="{ 'is-hidden': post.is_hidden, selected: board.isSelected(post.id) }"
      :data-post-id="post.id"
      @click="onCardClick($event, post)"
    >
      <!--
        Admin-only selection check, visible only in selection mode. Not CheckboxComponent because
        we need shift-click range + we drive `checked` from the store (not a form value), so the
        component's v-model shape doesn't fit.
      -->
      <input
        v-if="board.selectionMode"
        type="checkbox"
        class="card-check"
        :checked="board.isSelected(post.id)"
        :aria-label="`Выбрать «${post.title}»`"
        @click.stop="onCheck($event, post.id)"
      />
      <VoteButtons :post="post" stacked />
      <div class="card-body">
        <PostTags :post="post" :team="board.team" />
        <h2><button type="button" class="card-open" @click="open(post)">{{ post.title }}</button></h2>
        <div class="excerpt">{{ post.description }}</div>
        <PostMeta :post="post" reply-label />
      </div>
      <div class="card-side">
        <span class="pill" :data-color="post.status.color ?? 'none'">{{ post.status.name }}</span>
        <CardMenu v-if="board.team" :post="post" @move="move(post, $event)" @hide="toggleHidden(post)" />
        <CopyLink v-else :post-id="post.id" />
      </div>
    </article>
    <div v-if="!board.posts.length && !board.loading" class="empty">Пока ни одного предложения. Нажмите «Предложить», чтобы стать первым.</div>
  </div>
  <div v-if="board.page < board.lastPage" class="more">
    <button type="button" class="btn" :disabled="board.loading" :aria-busy="board.loadingMore" @click="board.loadPosts(true)">
      {{ board.loadingMore ? 'Загружаем…' : 'Показать ещё' }}
    </button>
  </div>
  <StatusReplyModal v-if="asking" :posts="asking.posts" :status="asking.status" @close="asking = null" />
  <BulkActionBar v-if="board.selectionMode" />
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import { useTriage } from '../composables/useTriage'
import { useTriageKeys } from '../composables/useTriageKeys'
import BulkActionBar from './BulkActionBar.vue'
import CardMenu from './CardMenu.vue'
import CopyLink from './CopyLink.vue'
import PostMeta from './PostMeta.vue'
import PostTags from './PostTags.vue'
import StatusReplyModal from './StatusReplyModal.vue'
import VoteButtons from './VoteButtons.vue'

const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const { open, onCardClick } = useCardOpen(emit)
const { asking, move, toggleHidden } = useTriage()
useTriageKeys({ move, toggleHidden })

// Click: let the native toggle happen (visual stays in lockstep), then sync the store.
// Shift+click: cancel the single-item toggle, extend the range from the anchor instead.
function onCheck(e: MouseEvent, id: number) {
  if (e.shiftKey) {
    e.preventDefault()
    board.selectRange(id)
    return
  }
  board.toggleSelect(id)
}
</script>