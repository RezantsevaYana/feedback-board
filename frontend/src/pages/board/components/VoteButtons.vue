<template>
  <span class="votes" :class="{ sm: small, stacked }">
    <span class="vote-pair">
      <button
        type="button"
        class="vote"
        :aria-pressed="post.my_vote === 'like'"
        :aria-label="post.my_vote === 'like' ? 'Убрать лайк' : 'Нравится'"
        title="Нравится"
        @click.stop="board.vote(post, 'like')"
      >
        <span class="emo" aria-hidden="true">👍</span>
        <span v-if="!canSeeVoters">{{ post.likes_count }}</span>
      </button>
      <button
        v-if="canSeeVoters"
        type="button"
        class="vote-count"
        :aria-label="`Показать, кто поставил лайк (${post.likes_count})`"
        @click.stop="openVoters('like')"
      >{{ post.likes_count }}</button>
    </span>
    <span class="vote-pair">
      <button
        type="button"
        class="vote dislike"
        :aria-pressed="post.my_vote === 'dislike'"
        :aria-label="post.my_vote === 'dislike' ? 'Убрать дизлайк' : 'Не нравится'"
        title="Не нравится"
        @click.stop="board.vote(post, 'dislike')"
      >
        <span class="emo" aria-hidden="true">💩</span>
        <span v-if="!canSeeVoters">{{ post.dislikes_count }}</span>
      </button>
      <button
        v-if="canSeeVoters"
        type="button"
        class="vote-count"
        :aria-label="`Показать, кто поставил дизлайк (${post.dislikes_count})`"
        @click.stop="openVoters('dislike')"
      >{{ post.dislikes_count }}</button>
    </span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Post, VoteValue } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useVotersStore } from '@/store/modals/useVotersStore'

// 👍 / 💩: one reaction per browser; clicking the active one cancels it, the other one switches.
// For the triage team (owner / admin / manager) the counter is a separate button that opens
// the «кто голосовал» modal; for everyone else it stays inside the vote button, as a number only.
const props = defineProps<{
  post: Post
  small?: boolean
  stacked?: boolean
}>()
const board = useBoardStore()
const voters = useVotersStore()

const canSeeVoters = computed(() => board.role === 'owner' || board.role === 'admin' || board.role === 'manager')

function openVoters(kind: VoteValue) {
  voters.open(props.post.id, kind)
}
</script>