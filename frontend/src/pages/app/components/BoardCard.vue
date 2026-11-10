<template>
  <article class="board-card">
    <!-- the whole card opens the board (a stretched link under everything); the chips and the cabinet's buttons sit above it -->
    <a :href="`/${board.slug}`" target="_blank" rel="noopener" class="board-card-link" :aria-label="`Открыть доску ${board.name} в новой вкладке`" />

    <div class="board-card-top">
      <h3 class="board-card-name">{{ board.name }}</h3>
      <!-- what needs attention comes first: people waiting for access, posts waiting for triage -->
      <div class="board-card-needs">
        <RouterLink
          v-if="pending"
          :to="`/app/boards/${board.slug}/access#requests`"
          class="board-card-need requests"
          :title="`Открыть запросы на доступ`"
        >
          {{ pending }} {{ plural(pending, 'запрос', 'запроса', 'запросов') }} доступа
        </RouterLink>
        <span v-if="board.queue_count > 0" class="board-card-need fresh">{{ board.queue_count }} ждут разбора</span>
        <span v-if="!pending && board.queue_count === 0" class="board-card-calm">{{ board.last_post_at ? 'всё разобрано' : 'постов пока нет' }}</span>
      </div>
    </div>

    <div class="board-card-who">
      <VisibilityBadge :visibility="board.visibility" />
      <span class="board-card-role">{{ ROLE_LABELS[board.role] }}</span>
    </div>
    <p class="board-card-stats">{{ stats }}</p>

    <div class="board-card-foot">
      <span class="board-card-open" aria-hidden="true">Перейти на доску <span class="board-card-arrow">→</span></span>
      <div v-if="configure" class="board-card-actions">
        <RouterLink :to="`/app/boards/${board.slug}/settings`" class="btn btn-sm">Настройки</RouterLink>
        <RouterLink :to="`/app/boards/${board.slug}/access`" class="btn btn-sm">Доступ</RouterLink>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Board } from '@/api/boards'
import { ROLE_LABELS } from '@/api/types'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { fmtTimeAgo, plural } from '@/shared/utils/format'
import { canConfigure } from '@/shared/utils/roles'
import VisibilityBadge from '@/shared/components/VisibilityBadge.vue'

// A board on «Мои доски» (prototype frontend/demo/boards-hub-prototype.html): the card opens the board in a new tab,
// «Настройки» and «Доступ» are its own buttons for owner / admin. What needs attention is the loudest thing on it.
const props = defineProps<{
  board: Board
}>()
const auth = useAuthStore()

const configure = computed(() => canConfigure(props.board.role))
// access requests ride on /me, and only those who decide get a count
const pending = computed(() => auth.me?.projects.find((b) => b.slug === props.board.slug)?.pending_requests ?? 0)
const stats = computed(() => {
  const people = `${props.board.members_count} ${plural(props.board.members_count, 'участник', 'участника', 'участников')}`
  return props.board.last_post_at ? `${people} · пост ${fmtTimeAgo(props.board.last_post_at)}` : people
})
</script>