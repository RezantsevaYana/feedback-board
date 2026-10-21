<template>
  <div class="list">
    <article
      v-for="post in board.posts"
      :key="post.id"
      class="card"
      :class="{ 'is-hidden': post.is_hidden }"
      :data-post-id="post.id"
      @click="onCardClick($event, post)"
    >
      <VoteButtons :post="post" stacked />
      <div class="card-body">
        <PostTags :post="post" :team="board.team" />
        <h2><button type="button" class="card-open" @click="open(post)">{{ post.title }}</button></h2>
        <div class="excerpt">{{ post.description }}</div>
        <PostMeta :post="post" reply-label />
      </div>
      <div class="card-side">
        <span class="pill" :data-s="post.status.slug">{{ post.status.name }}</span>
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
  <StatusReplyModal v-if="asking" :post="asking.post" :status="asking.status" @close="asking = null" />
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import { useTriage } from '../composables/useTriage'
import { useTriageKeys } from '../composables/useTriageKeys'
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
</script>