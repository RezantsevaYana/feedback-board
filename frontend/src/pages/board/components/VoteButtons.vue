<template>
  <span class="votes" :class="{ sm: small, stacked }">
    <button
      type="button"
      class="vote"
      :aria-pressed="post.my_vote === 'like'"
      :aria-label="post.my_vote === 'like' ? 'Убрать лайк' : 'Нравится'"
      title="Нравится"
      @click.stop="board.vote(post, 'like')"
    >
      <span class="emo" aria-hidden="true">👍</span><span>{{ post.likes_count }}</span>
    </button>
    <button
      type="button"
      class="vote dislike"
      :aria-pressed="post.my_vote === 'dislike'"
      :aria-label="post.my_vote === 'dislike' ? 'Убрать дизлайк' : 'Не нравится'"
      title="Не нравится"
      @click.stop="board.vote(post, 'dislike')"
    >
      <span class="emo" aria-hidden="true">💩</span><span>{{ post.dislikes_count }}</span>
    </button>
  </span>
</template>

<script setup lang="ts">
import type { Post } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'

// 👍 / 💩: one reaction per browser; clicking the active one cancels it, the other one switches.
defineProps<{
  post: Post
  small?: boolean
  stacked?: boolean
}>()
const board = useBoardStore()
</script>
