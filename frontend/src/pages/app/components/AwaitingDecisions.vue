<template>
  <section class="incoming-invites" aria-labelledby="awaiting-decisions-title">
    <h2 id="awaiting-decisions-title">Ждут решения на ваших досках · {{ total }}</h2>
    <ul class="incoming-invites-list">
      <li v-for="b in boards" :key="b.id" class="incoming-invite">
        <div class="incoming-invite-main">
          <div class="incoming-invite-board">{{ b.name }}</div>
          <div class="incoming-invite-meta">{{ plural(b.pending_requests ?? 0) }}</div>
        </div>
        <div class="incoming-invite-actions">
          <RouterLink :to="`/app/boards/${b.slug}/participants`" class="btn btn-primary">Рассмотреть</RouterLink>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { BoardSummary } from '@/api/types'

// «Ждут решения на ваших досках» in the hub: boards where someone asked for access and I'm owner / admin.
// The decision itself is on the board's participants page; the block goes away when nothing waits.
const props = defineProps<{
  boards: BoardSummary[]
}>()

const total = computed(() => props.boards.reduce((sum, b) => sum + (b.pending_requests ?? 0), 0))

function plural(n: number): string {
  const mod10 = n % 10
  const mod100 = n % 100
  const word = mod10 === 1 && mod100 !== 11 ? 'запрос' : mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? 'запроса' : 'запросов'
  return `${n} ${word} на доступ`
}
</script>
