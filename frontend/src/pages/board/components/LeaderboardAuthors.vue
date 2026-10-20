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
    <article
      v-for="entry in board.leaderboard"
      :key="entry.token"
      class="leader-row"
      :class="{ top: entry.place <= 3, me: entry.token === myToken }"
    >
      <span class="rank-n" :aria-label="`Место ${entry.place}`">
        <span v-if="entry.place <= 3" aria-hidden="true">{{ medal(entry.place) }}</span>
        <template v-else>{{ entry.place }}</template>
      </span>
      <div class="card-body">
        <h3>
          {{ entry.name }}
          <span v-if="entry.token === myToken" class="you-chip">вы</span>
        </h3>
        <div class="meta">{{ metricLabel(entry.value) }}</div>
      </div>
      <span v-if="deltaFor(entry) !== null" class="delta" :data-dir="deltaDirection(entry)">
        {{ deltaLabel(entry) }}
      </span>
    </article>
    <div v-if="!board.leaderboard.length && !board.loading" class="empty">{{ emptyMessage }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue'
import type { LeaderboardEntry } from '@/api/leaderboard'
import { fireConfetti } from '@/shared/utils/confetti'
import { voterToken } from '@/shared/utils/voter'
import { useBoardStore } from '@/store/board/useBoardStore'

// Лидерборд: топ 20 авторов за всё время или за 7 дней, по двум метрикам —
// числу идей или сумме голосов (лайки минус дизлайки). Своя строка подсвечивается,
// топ-3 с медалями, рядом с местом — изменение с прошлого визита. Когда ты впервые
// оказываешься на #1, один раз шелохнётся салют.
const board = useBoardStore()
const myToken = voterToken()

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

const MEDALS: Record<number, string> = { 1: '🥇', 2: '🥈', 3: '🥉' }
function medal(place: number): string {
  return MEDALS[place] ?? String(place)
}

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

// --- Снапшот предыдущей выдачи для подсчёта Δ и триггера салюта ----------
// {token: place} в localStorage отдельно по (проект, scope, metric), чтобы
// переключение фильтра не плодило липовых «движений». Baseline фиксируем на
// сессию — иначе Δ сразу схлопывалась бы в ноль, а салют стрелял при каждом
// возврате на #1 во время пулинга.
type Snapshot = Record<string, number>
const snapshotKey = computed(
  () => `fb-leader-snap-${board.project?.slug ?? '_'}-${board.view.leaderboardScope}-${board.view.leaderboardMetric}`,
)

function readSnapshot(key: string): Snapshot {
  try {
    const raw = localStorage.getItem(key)
    return raw ? (JSON.parse(raw) as Snapshot) : {}
  } catch {
    return {}
  }
}
function writeSnapshot(key: string, data: readonly LeaderboardEntry[]): void {
  if (!data.length) return
  try {
    const snap: Snapshot = Object.fromEntries(data.map((e) => [e.token, e.place]))
    localStorage.setItem(key, JSON.stringify(snap))
  } catch {
    // storage недоступен — живём без Δ
  }
}

let baseline: Snapshot = readSnapshot(snapshotKey.value)
const salutedKeys = new Set<string>()

function deltaFor(entry: LeaderboardEntry): number | null {
  const prev = baseline[entry.token]
  if (prev === undefined || prev === entry.place) return null
  return prev - entry.place
}
function deltaDirection(entry: LeaderboardEntry): 'up' | 'down' {
  return (deltaFor(entry) ?? 0) > 0 ? 'up' : 'down'
}
function deltaLabel(entry: LeaderboardEntry): string {
  const d = deltaFor(entry)
  if (d === null) return ''
  return d > 0 ? `▲${d}` : `▼${-d}`
}

// Салют: один раз на (проект, scope, metric) за сессию, когда ты впервые видишь
// себя на #1 и в прошлом снапшоте ты не был первым. Baseline не трогаем —
// подпись Δ должна сохраняться стабильной до конца сессии.
watch(
  () => board.leaderboard,
  (data) => {
    if (!data.length) return
    const me = data.find((e) => e.token === myToken)
    const key = snapshotKey.value
    if (me?.place === 1 && baseline[myToken] !== 1 && !salutedKeys.has(key)) {
      fireConfetti()
      salutedKeys.add(key)
    }
    // Пишем в localStorage сразу — чтобы при закрытии вкладки свежая выдача
    // стала baseline для следующего визита. На текущую сессию это не влияет.
    writeSnapshot(key, data)
  },
  { immediate: true },
)

// Смена проекта/скоупа/метрики — перечитываем baseline из нужной ячейки.
// Текущая выдача будет записана в новую ячейку при следующем обновлении.
watch(snapshotKey, (key) => {
  baseline = readSnapshot(key)
})

// На выход со вкладки — ещё раз зафиксировать актуальное состояние, вдруг
// данные успели прийти после последнего write (например, из пулинга).
onBeforeUnmount(() => writeSnapshot(snapshotKey.value, board.leaderboard))
</script>
