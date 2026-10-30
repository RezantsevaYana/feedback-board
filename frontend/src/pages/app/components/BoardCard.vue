<template>
  <article class="board-card">
    <!-- the board opens in a new tab, the cabinet stays where it was; the arrow slides in on hover -->
    <a :href="`/${board.slug}`" class="board-card-top" target="_blank" rel="noopener" :aria-label="`${board.name} — открыть доску в новой вкладке`">
      <span class="board-card-name-row">
        <h3 class="board-card-name">{{ board.name }}</h3>
        <span class="board-card-go" aria-hidden="true">↗</span>
      </span>
      <code class="board-card-slug">/{{ board.slug }}</code>
    </a>
    <div class="board-card-meta">
      <span class="board-card-queue" :class="{ pending: board.queue_count > 0 }">
        <template v-if="board.queue_count > 0">
          {{ board.queue_count }} новых
        </template>
        <template v-else>Пусто</template>
      </span>
      <span class="board-card-last">{{ lastActivity }}</span>
    </div>
    <!-- my role on this board, and whether it's open to anyone with the link -->
    <div class="board-card-role">
      <span class="role-chip" :class="board.role">{{ ROLE_LABELS[board.role] }}</span>
      <VisibilityBadge :visibility="board.visibility" />
    </div>
    <div v-if="configure" class="board-card-actions">
      <RouterLink :to="`/app/boards/${board.slug}/settings`" class="btn">Настройки</RouterLink>
      <RouterLink :to="`/app/boards/${board.slug}/participants`" class="btn">Участники</RouterLink>
      <RouterLink :to="`/app/boards/${board.slug}/embed`" class="btn">Как подключить</RouterLink>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Board } from '@/api/boards'
import { ROLE_LABELS } from '@/api/types'
import { fmtTimeAgo, plural } from '@/shared/utils/format'
import { canConfigure } from '@/shared/utils/roles'
import VisibilityBadge from '@/shared/components/VisibilityBadge.vue'

const props = defineProps<{
  board: Board
}>()

const configure = computed(() => canConfigure(props.board.role))
const lastActivity = computed(() => {
  const people = `${props.board.members_count} ${plural(props.board.members_count, 'участник', 'участника', 'участников')}`
  const last = props.board.last_post_at ? `посл. пост ${fmtTimeAgo(props.board.last_post_at)}` : 'постов ещё нет'
  return `${people} · ${last}`
})
</script>