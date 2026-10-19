<script setup lang="ts">
import type { Post } from '@/api/types'
import { STATUS } from '@/lib/dict'
import { useBoardStore } from '@/stores/board'
import PostMeta from './PostMeta.vue'
import PostTags from './PostTags.vue'
import VoteButtons from './VoteButtons.vue'

const emit = defineEmits<{ open: [post: Post] }>()
const board = useBoardStore()
</script>

<template>
  <div class="list">
    <article v-for="post in board.posts" :key="post.id" class="card" :class="{ 'is-hidden': post.is_hidden }">
      <VoteButtons :post="post" stacked />
      <div class="card-body" role="link" tabindex="0" @click="emit('open', post)" @keydown.enter="emit('open', post)">
        <PostTags :post="post" :team="board.team" />
        <h3>{{ post.title }}</h3>
        <div class="excerpt">{{ post.description }}</div>
        <PostMeta :post="post" reply-label />
      </div>
      <span class="pill" :data-s="post.status">{{ STATUS[post.status] }}</span>
    </article>
    <div v-if="!board.posts.length && !board.loading" class="empty">Под эти фильтры ничего не нашлось.</div>
  </div>
  <div v-if="board.page < board.lastPage" class="more">
    <button type="button" class="btn" :disabled="board.loading" @click="board.loadPosts(true)">
      {{ board.loading ? 'Загружаем…' : 'Показать ещё' }}
    </button>
  </div>
</template>