<template>
  <div class="board-head">
    <div class="board-title">
      <h1>Топ</h1>
      <p>Предложения по голосам: лайки минус дизлайки. Команда видит, что нужно в первую очередь.</p>
    </div>
  </div>
  <div class="filters">
    <CategoryChips />
    <span class="spacer" />
    <div class="chips">
      <button type="button" :aria-pressed="board.view.topScope === 'open'" @click="board.view.topScope = 'open'">Ещё не сделаны</button>
      <button type="button" :aria-pressed="board.view.topScope === 'all'" @click="board.view.topScope = 'all'">Все, включая готовые</button>
    </div>
  </div>
  <BoardSkeleton v-if="board.loading && !board.ranking.length" layout="rows" />
  <div v-else class="rank">
    <article
      v-for="{ post, place } in board.ranking"
      :key="post.id"
      class="rank-row"
      :class="{ top: place <= 3 && score(post) > 0 }"
      tabindex="0"
      role="link"
      :aria-label="post.title"
      @click="onCardClick($event, post)"
      @keydown.enter.prevent="onCardEnter(post)"
    >
      <span class="rank-n" :aria-label="`Место ${place}`">{{ place }}</span>
      <div class="card-body">
        <h3>{{ post.title }}</h3>
        <div class="meta">
          <CategoryTag :category="post.category" />
          <span class="pill" :data-color="post.status.color ?? 'none'">{{ post.status.name }}</span>
          <span>{{ post.author_name || TEXT.anonymous }}</span>
          <span>{{ fmtDate(post.created_at) }}</span>
        </div>
      </div>
      <div class="rank-actions">
        <CopyLink :post-id="post.id" />
        <VoteButtons :post="post" small />
      </div>
    </article>
    <div v-if="!board.ranking.length && !board.loading" class="empty">Пока нечего ранжировать.</div>
  </div>
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { TEXT } from '@/shared/constants/dict'
import { fmtDate } from '@/shared/utils/format'
import { score, useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import BoardSkeleton from './BoardSkeleton.vue'
import CategoryChips from './CategoryChips.vue'
import CategoryTag from './CategoryTag.vue'
import CopyLink from './CopyLink.vue'
import VoteButtons from './VoteButtons.vue'

// Ranking of everything not rejected by likes minus dislikes, optionally within one category.
const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const { onCardClick, onCardEnter } = useCardOpen(emit)
</script>
