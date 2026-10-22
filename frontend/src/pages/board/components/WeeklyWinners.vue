<template>
  <div class="board-head">
    <div class="board-title">
      <h1>Хиты недели</h1>
      <p>
        По одному победителю в каждой категории — за неделю, по сумме голосов «за» минус «против».
        <span v-if="rangeLabel" class="weekly-range">{{ rangeLabel }}</span>
      </p>
    </div>
  </div>
  <BoardSkeleton v-if="board.loading && !board.weeklyWinners.length" layout="rows" />
  <div v-else class="weekly-grid">
    <article
      v-for="(entry, i) in board.weeklyWinners"
      :key="entry.category.id"
      class="weekly-card"
      :class="{ 'has-post': !!entry.post, empty: !entry.post }"
      :data-color="entry.category.color ?? 'none'"
      :style="{ '--i': i }"
      :tabindex="entry.post ? 0 : -1"
      :role="entry.post ? 'link' : undefined"
      :aria-label="entry.post?.title"
      @click="entry.post && onCardClick($event, entry.post)"
      @keydown.enter.prevent="entry.post && onCardEnter(entry.post)"
    >
      <header class="weekly-head">
        <CategoryTag :category="entry.category" />
        <span v-if="entry.post" class="weekly-crown" aria-hidden="true">🏆</span>
      </header>
      <template v-if="entry.post">
        <h3 class="weekly-title">{{ entry.post.title }}</h3>
        <div class="weekly-byline">
          <span class="who">{{ entry.post.author_name || TEXT.anonymous }}</span>
          <span class="weekly-score" :data-dir="scoreDir(entry.post)">{{ scoreLabel(entry.post) }}</span>
        </div>
        <footer class="weekly-foot">
          <span class="when">{{ fmtDate(entry.post.created_at) }}</span>
          <div class="weekly-actions">
            <CopyLink :post-id="entry.post.id" />
            <VoteButtons :post="entry.post" small />
          </div>
        </footer>
      </template>
      <div v-else class="weekly-empty">
        <p>Пока никто не отличился.</p>
        <p class="muted">На этой неделе здесь пусто — у вас шанс стать первым.</p>
      </div>
    </article>
    <div v-if="!board.weeklyWinners.length && !board.loading" class="empty">В проекте ещё нет категорий.</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Post } from '@/api/types'
import { TEXT } from '@/shared/constants/dict'
import { fmtDate } from '@/shared/utils/format'
import { score, useBoardStore } from '@/store/board/useBoardStore'
import { useCardOpen } from '../composables/useCardOpen'
import BoardSkeleton from './BoardSkeleton.vue'
import CategoryTag from './CategoryTag.vue'
import CopyLink from './CopyLink.vue'
import VoteButtons from './VoteButtons.vue'

const emit = defineEmits<{
  open: [post: Post]
}>()
const board = useBoardStore()
const { onCardClick, onCardEnter } = useCardOpen(emit)

const rangeLabel = computed(() => {
  const meta = board.weeklyWinnersMeta
  if (!meta) return ''
  return `${fmtDate(meta.week_start)} — ${fmtDate(meta.week_end)}`
})

function scoreLabel(post: Post): string {
  const s = score(post)
  if (s > 0) return `+${s}`
  if (s < 0) return `${s}`
  return `${post.likes_count}`
}
function scoreDir(post: Post): 'up' | 'down' | 'zero' {
  const s = score(post)
  if (s > 0) return 'up'
  if (s < 0) return 'down'
  return 'zero'
}
</script>
