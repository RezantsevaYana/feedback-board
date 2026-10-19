<template>
  <div class="board-head">
    <h1>Топ</h1>
    <p>Предложения по голосам: лайки минус дизлайки. Команда видит, что нужно в первую очередь.</p>
  </div>
  <div class="filters">
    <CategoryChips />
    <span class="spacer" />
    <div class="chips">
      <button type="button" :aria-pressed="board.view.topScope === 'open'" @click="board.view.topScope = 'open'">Ещё не сделаны</button>
      <button type="button" :aria-pressed="board.view.topScope === 'all'" @click="board.view.topScope = 'all'">Все, включая готовые</button>
    </div>
  </div>
  <div class="rank">
    <article v-for="{ post, place } in board.ranking" :key="post.id" class="rank-row" :class="{ top: place <= 3 && score(post) > 0 }">
      <span class="rank-n" :aria-label="`Место ${place}`">{{ place }}</span>
      <div class="card-body" role="link" tabindex="0" @click="emit('open', post)" @keydown.enter="emit('open', post)">
        <h3>{{ post.title }}</h3>
        <div class="meta">
          <CategoryTag :category="post.category" />
          <span class="pill" :data-s="post.status">{{ STATUS[post.status] }}</span>
          <span>{{ post.author_name || TEXT.anonymous }}</span>
          <span>{{ fmtDate(post.created_at) }}</span>
        </div>
      </div>
      <VoteButtons :post="post" small />
    </article>
    <div v-if="!board.ranking.length && !board.loading" class="empty">Пока нечего ранжировать.</div>
  </div>
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { STATUS, TEXT } from '@/shared/constants/dict'
import { fmtDate } from '@/shared/utils/format'
import { score, useBoardStore } from '@/store/board/useBoardStore'
import CategoryChips from './CategoryChips.vue'
import CategoryTag from './CategoryTag.vue'
import VoteButtons from './VoteButtons.vue'

// Ranking of everything not rejected by likes minus dislikes, optionally within one category.
const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
</script>
