<template>
  <div class="board-head">
    <div class="board-title">
      <h1>Топ</h1>
      <p>Рейтинг по голосам: лайки минус дизлайки. При одинаковом результате выше более раннее обращение.</p>
    </div>
  </div>
  <div class="filters">
    <CategoryChips />
    <span class="spacer" />
    <CheckboxComponent v-model="includeDone" label="Включая готовые" />
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
          <span class="pill" :data-color="post.status.color ?? 'none'" :style="entityColorStyle(post.status.color)">{{ post.status.name }}</span>
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
import { computed } from 'vue'
import CheckboxComponent from '@/shared/ui/CheckboxComponent.vue'
import { entityColorStyle } from '@/shared/utils/entityColor'
import type { Post } from '@/api/types'
import { TEXT } from '@/shared/constants/dict'
import { fmtDate } from '@/shared/utils/format'
import { score, useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import BoardSkeleton from './BoardSkeleton.vue'
import CategoryChips from './CategoryChips.vue'
import CategoryTag from '@/shared/components/CategoryTag.vue'
import CopyLink from './CopyLink.vue'
import VoteButtons from './VoteButtons.vue'

// Ranking of everything not rejected by likes minus dislikes, optionally within one category.
const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const includeDone = computed({
  get: () => board.view.topScope === 'all',
  set: (value: boolean) => { board.view.topScope = value ? 'all' : 'open' },
})
const { onCardClick, onCardEnter } = useCardOpen(emit)
</script>
