<template>
  <div class="board-head">
    <h1>Авторы</h1>
    <p>{{ metricExplainer }} Чтобы попасть в топ — укажите имя при подаче предложения.</p>
  </div>
  <div class="filters">
    <div class="seg-icons" role="group" aria-label="Ранжировать по">
      <button type="button" :aria-pressed="board.view.leaderboardMetric === 'posts'" @click="board.view.leaderboardMetric = 'posts'">Больше идей</button>
      <button type="button" :aria-pressed="board.view.leaderboardMetric === 'support'" @click="board.view.leaderboardMetric = 'support'">Больше поддержки</button>
    </div>
    <span class="spacer" />
    <div class="chips">
      <button type="button" :aria-pressed="board.view.leaderboardScope === 'all'" @click="board.view.leaderboardScope = 'all'">За всё время</button>
      <button type="button" :aria-pressed="board.view.leaderboardScope === 'week'" @click="board.view.leaderboardScope = 'week'">За неделю</button>
    </div>
  </div>
  <div class="leader">
    <article v-for="entry in board.leaderboard" :key="entry.place" class="leader-row" :class="{ top: entry.place <= 3 }">
      <span class="rank-n" :aria-label="`Место ${entry.place}`">{{ entry.place }}</span>
      <div class="card-body">
        <h3>{{ entry.name }}</h3>
        <div class="meta">{{ metricLabel(entry.value) }}</div>
      </div>
    </article>
    <div v-if="!board.leaderboard.length && !board.loading" class="empty">{{ emptyMessage }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useBoardStore } from '@/store/board/useBoardStore'

// Лидерборд: топ 20 авторов за всё время или за 7 дней, по двум метрикам —
// числу идей или сумме голосов (лайки минус дизлайки). В списке только те,
// кто засветил имя при подаче предложения, анонимы в выборку не попадают.
const board = useBoardStore()

const metricExplainer = computed(() =>
  board.view.leaderboardMetric === 'support'
    ? 'Чьи идеи собрали больше голосов «за» минус «против».'
    : 'Кто чаще предлагает идеи — считаем число опубликованных предложений.',
)

const emptyMessage = computed(() =>
  board.view.leaderboardScope === 'week'
    ? 'На этой неделе никто ещё не отметился именем.'
    : 'Пока нет авторов с именем. Подайте предложение и укажите имя — попадёте в топ.',
)

// 1 идея / 2 идеи / 5 идей; +12 / −3 поддержки для метрики support.
function metricLabel(value: number): string {
  if (board.view.leaderboardMetric === 'support') {
    const prefix = value > 0 ? '+' : ''
    return `${prefix}${value} ${plural(Math.abs(value), 'поддержка', 'поддержки', 'поддержек')}`
  }
  return `${value} ${plural(value, 'идея', 'идеи', 'идей')}`
}

function plural(n: number, one: string, few: string, many: string): string {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return one
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few
  return many
}
</script>
