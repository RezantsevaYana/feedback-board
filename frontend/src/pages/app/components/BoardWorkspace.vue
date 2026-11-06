<template>
  <div class="app-page">
    <div class="app-page-head">
      <div class="app-page-title">
        <h1>{{ title ?? PAGE_TITLES[page] }}</h1>
        <div class="app-page-actions">
          <!-- kept on every section (TEST.md п. 42 decision), even on «Общее» next to its link block -->
          <!-- phone: both are small outlined icons on the title's row; the words stay for screen readers -->
          <CopyButton :value="boardUrl" label="Скопировать ссылку" class="app-page-copy" title="Скопировать ссылку на доску" />
          <a :href="boardUrl" target="_blank" rel="noopener" class="btn btn-primary app-page-open" aria-label="Открыть доску в новой вкладке" title="Открыть доску в новой вкладке">
            <span class="app-page-open-full" aria-hidden="true">Перейти на доску →</span><span class="app-page-open-short" aria-hidden="true">↗</span>
          </a>
        </div>
      </div>
      <p v-if="$slots.lead" class="page-lead"><slot name="lead" /></p>
    </div>
    <!-- tablet / phone: the side menu is behind «Меню», so the board's sections are tabs here (TEST.md п. 42) -->
    <nav ref="tabs" class="app-page-tabs" aria-label="Настройки доски" @scroll.passive="rememberScroll">
      <RouterLink
        v-for="p in BOARD_PAGES"
        :key="p.page"
        :to="`/app/boards/${slug}/${p.page}`"
        class="app-page-tab"
        :aria-current="p.page === page ? 'page' : undefined"
      >
        {{ p.label }}<span v-if="p.page === 'access' && pendingRequests" class="count-badge">{{ pendingRequests }}</span>
      </RouterLink>
    </nav>
    <slot />
  </div>
</template>

<script lang="ts">
// where the tabs row was scrolled, and whether it was shown at all yet: kept between the sections' pages
// (each builds the row anew), see showCurrentTab below
let tabsScroll = 0
let tabsShown = false
</script>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { BOARD_PAGES, type BoardPage } from '@/shared/constants/boardPages'
import CopyButton from '@/shared/ui/CopyButton.vue'

// Head of a board's settings section in the cabinet (Общее / Доступ / Категории и статусы / Виджет): the page's name, the board's link to share
// and «Перейти на доску» (opens in a new tab so the cabinet stays available). The switch between the pages is the side menu.
const props = defineProps<{
  slug: string
  page: BoardPage
  // replaces the page's name in the heading (e.g. «Доска «…» готова» right after creating it)
  title?: string
}>()

const PAGE_TITLES = Object.fromEntries(BOARD_PAGES.map((p) => [p.page, p.label])) as Record<BoardPage, string>
const auth = useAuthStore()
// the open section's tab, scrolled into the row's view (on a phone «Виджет» is past the right edge).
// Every section is a page of its own, so the row is built anew on each switch: it starts where the previous one
// was and glides to the new tab, instead of jumping from the row's start.
const tabs = ref<HTMLElement | null>(null)
function showCurrentTab(smooth: boolean) {
  const row = tabs.value
  const current = row?.querySelector<HTMLElement>('[aria-current="page"]')
  if (!row || !current) return
  const left = current.offsetLeft - (row.clientWidth - current.offsetWidth) / 2
  const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  row.scrollTo({ left, behavior: smooth && !still ? 'smooth' : 'auto' })
}
function rememberScroll() {
  tabsScroll = tabs.value?.scrollLeft ?? 0
}
onMounted(() => {
  if (tabs.value) tabs.value.scrollLeft = tabsScroll
  // the first visit lands right on its tab; a switch from another section glides there
  showCurrentTab(tabsShown)
  tabsShown = true
})
watch(() => props.page, () => nextTick(() => showCurrentTab(true)))
// access requests waiting on this board: the «Доступ» tab counts them, as the side menu does
const pendingRequests = computed(() => auth.me?.projects.find((b) => b.slug === props.slug)?.pending_requests ?? 0)
// the board lives in this same app, at /<slug>
const boardUrl = computed(() => `${window.location.origin}/${props.slug}`)
</script>