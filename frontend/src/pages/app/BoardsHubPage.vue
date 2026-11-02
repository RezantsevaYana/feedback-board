<template>
  <div class="boards-hub">
    <IncomingInvitations v-if="auth.incoming.length" :invitations="auth.incoming" />
    <AwaitingDecisions v-if="auth.awaitingDecision.length" :boards="auth.awaitingDecision" />
    <section v-if="auth.boardsLoading && !auth.boardsLoaded" class="boards-hub-loading">
      <p class="note">Загружаем…</p>
    </section>
    <!-- first visit: a big greeting on the left, one line on what a board is for, one big button -->
    <section v-else-if="auth.boards.length === 0" class="boards-hub-hello">
      <h1>Привет, {{ auth.me?.name }}</h1>
      <p class="boards-hub-hello-lead">Создайте первую доску: сюда ваш продукт будет присылать баги и идеи.</p>
      <p class="note">Подключите виджет на сайт или просто поделитесь ссылкой на доску.</p>
      <!-- hover rolls the label into «Поехали →», same as «Предложить» on the board -->
      <RouterLink to="/app/boards/new" class="btn btn-lg btn-primary" aria-label="Создать первую доску">
        <span class="cta-roll" aria-hidden="true"><span>Создать первую доску</span><span>Поехали →</span></span>
      </RouterLink>
    </section>
    <template v-else>
      <!-- on a phone the button leaves the head and pins to the bottom, like «Предложить» on the board;
           hover rolls the label into «Поехали →» (shared .cta-roll) -->
      <div class="boards-hub-top">
        <h1>Мои доски</h1>
        <RouterLink to="/app/boards/new" class="btn btn-primary boards-hub-new" aria-label="Новая доска">
          <span class="cta-roll" aria-hidden="true"><span>+ Новая доска</span><span>Поехали →</span></span>
        </RouterLink>
      </div>
      <!-- the two groups get headings only when both exist: an owner with their own boards sees one plain grid -->
      <h2 v-if="split" class="boards-hub-group">Я веду · {{ leading.length }}</h2>
      <div v-if="leading.length" class="boards-grid">
        <BoardCard v-for="b in leading" :key="b.id" :board="b" />
      </div>
      <h2 v-if="split" class="boards-hub-group">Я участник · {{ taking.length }}</h2>
      <div v-if="taking.length" class="boards-grid">
        <BoardCard v-for="b in taking" :key="b.id" :board="b" />
      </div>
    </template>
    <TrashSection v-if="auth.boardsLoaded && auth.trashed.length" :boards="auth.trashed" />
  </div>

</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import type { Board } from '@/api/boards'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { canConfigure } from '@/shared/utils/roles'
import AwaitingDecisions from './components/AwaitingDecisions.vue'
import BoardCard from './components/BoardCard.vue'
import IncomingInvitations from './components/IncomingInvitations.vue'
import TrashSection from './components/TrashSection.vue'

const auth = useAuthStore()

// «Я веду» — where I decide (owner / admin), «Я участник» — where I was invited to take part.
// Inside each group what needs attention comes first (access requests, then posts in «Новое»), then the latest activity
const pendingOf = (slug: string) => auth.me?.projects.find((p) => p.slug === slug)?.pending_requests ?? 0
function byAttention(a: Board, b: Board): number {
  return pendingOf(b.slug) - pendingOf(a.slug)
    || b.queue_count - a.queue_count
    || (b.last_post_at ?? '').localeCompare(a.last_post_at ?? '')
}
const leading = computed(() => auth.boards.filter((b) => canConfigure(b.role)).sort(byAttention))
const taking = computed(() => auth.boards.filter((b) => !canConfigure(b.role)).sort(byAttention))
const split = computed(() => leading.value.length > 0 && taking.value.length > 0)

onMounted(async () => {
  // invitations are a bonus on top of the hub: a failure there must not break the boards
  void auth.fetchIncoming().catch(() => undefined)
  // the waiting-requests counts ride on /me; the hub is where they're looked at, so they're fetched fresh
  void auth.fetchMe(true).catch(() => undefined)
  if (!auth.boardsLoaded) await auth.fetchBoards()
})
</script>