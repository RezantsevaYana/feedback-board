<template>
  <article class="board-card">
    <RouterLink :to="`/${board.slug}`" class="board-card-top">
      <h3 class="board-card-name">{{ board.name }}</h3>
      <code class="board-card-slug">/{{ board.slug }}</code>
    </RouterLink>
    <div class="board-card-meta">
      <span class="board-card-queue" :class="{ pending: board.queue_count > 0 }">
        <template v-if="board.queue_count > 0">
          {{ board.queue_count }} в очереди
        </template>
        <template v-else>Очередь пуста</template>
      </span>
      <span class="board-card-last">{{ lastActivity }}</span>
    </div>
    <div class="board-card-actions">
      <RouterLink :to="`/app/boards/${board.slug}/settings`" class="link-btn">Настройки</RouterLink>
      <RouterLink :to="`/app/boards/${board.slug}/embed`" class="link-btn">Как подключить</RouterLink>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { Board } from '@/api/boards'
import { fmtTimeAgo } from '@/shared/utils/format'

const props = defineProps<{
  board: Board
}>()

const lastActivity = computed(() => (props.board.last_post_at ? `посл. пост ${fmtTimeAgo(props.board.last_post_at)}` : 'постов ещё нет'))
</script>