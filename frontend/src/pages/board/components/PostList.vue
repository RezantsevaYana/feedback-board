<template>
  <div class="list">
    <article
      v-for="post in board.posts"
      :key="post.id"
      class="card"
      :class="{ 'is-hidden': post.is_hidden }"
      tabindex="0"
      role="link"
      :aria-label="post.title"
      @click="onCardClick($event, post)"
      @keydown.enter.prevent="onCardEnter(post)"
    >
      <VoteButtons :post="post" stacked />
      <div class="card-body">
        <PostTags :post="post" :team="board.team" />
        <h3>{{ post.title }}</h3>
        <div class="excerpt">{{ post.description }}</div>
        <PostMeta :post="post" reply-label />
      </div>
      <div class="card-side">
        <span class="pill" :data-s="post.status.slug">{{ post.status.name }}</span>
        <CopyLink :post-id="post.id" />
      </div>
    </article>
    <div v-if="!board.posts.length && !board.loading" class="empty">Под эти фильтры ничего не нашлось.</div>
  </div>
  <div v-if="board.page < board.lastPage" class="more">
    <button type="button" class="btn" :disabled="board.loading" @click="board.loadPosts(true)">
      {{ board.loading ? 'Загружаем…' : 'Показать ещё' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import CopyLink from './CopyLink.vue'
import PostMeta from './PostMeta.vue'
import PostTags from './PostTags.vue'
import VoteButtons from './VoteButtons.vue'

const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const { onCardClick, onCardEnter } = useCardOpen(emit)
</script>
