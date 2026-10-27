<template>
  <nav ref="nav" class="settings-nav" aria-label="Разделы доски">
    <template v-for="item in items" :key="item.hash">
      <a
        v-if="page === 'settings'"
        :href="item.hash"
        class="settings-nav-item"
        :aria-current="current === item.hash ? 'location' : undefined"
        @click.prevent="pick(item.hash)"
      >
        {{ item.label }}
      </a>
      <RouterLink
        v-else
        :to="`/app/boards/${slug}/settings${item.hash}`"
        class="settings-nav-item"
      >
        {{ item.label }}
      </RouterLink>
      <!-- connection is its own page, but sits next to the domains it depends on -->
      <RouterLink
        v-if="item.hash === '#domains'"
        :to="`/app/boards/${slug}/embed`"
        class="settings-nav-item"
        :aria-current="page === 'embed' ? 'page' : undefined"
      >
        Подключение
      </RouterLink>
      <!-- participants: own page, after everything else -->
      <RouterLink
        v-if="item.hash === '#domains'"
        :to="`/app/boards/${slug}/participants`"
        class="settings-nav-item"
        :aria-current="page === 'participants' ? 'page' : undefined"
      >
        Участники
      </RouterLink>
    </template>
  </nav>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

// Side nav of a board's pages (BoardWorkspace). On the settings page the items are anchors to its sections
// and the section at the top of the screen is marked current, like the active section on the board;
// on the connection page they lead back to those sections and «Подключение» is current.
// Deletion isn't a section, so it's not here: it sits at the bottom of the settings page.
const props = defineProps<{
  slug: string
  page: 'settings' | 'embed' | 'participants'
}>()
const route = useRoute()
const router = useRouter()

const items = [
  { hash: '#general', label: 'Общее' },
  { hash: '#categories', label: 'Категории' },
  { hash: '#statuses', label: 'Столбцы' },
  { hash: '#domains', label: 'Домены' },
]
const current = ref(items.some((i) => i.hash === route.hash) ? route.hash : '#general')

// A jump (click, or arriving with #domains) marks that section and holds it while the page scrolls there.
// Plain scrolling marks the last section whose top has passed the upper third of the screen; at the very
// bottom the short last sections can't get that high, so the chosen one stays if it's on screen.
// The click scrolls smoothly instead of the anchor's instant jump; the hold ends when the scroll does
// (the timer is a fallback for browsers without scrollend).
let holdUntil = route.hash ? Date.now() + 1200 : 0
function pick(hash: string) {
  current.value = hash
  holdUntil = Date.now() + 1500
  const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  document.querySelector(hash)?.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto' })
  void router.replace({ hash })
}
function onScrollEnd() {
  holdUntil = 0
}

let frame = 0
function onScroll() {
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(() => {
    if (Date.now() < holdUntil) return
    const tops = items
      .map((i) => ({ hash: i.hash, top: document.querySelector(i.hash)?.getBoundingClientRect().top }))
      .filter((s): s is { hash: string; top: number } => s.top !== undefined)
    const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2
    const chosen = tops.find((s) => s.hash === current.value)
    if (atBottom && chosen && chosen.top < window.innerHeight) return
    const passed = tops.filter((s) => s.top <= window.innerHeight / 3)
    current.value = atBottom ? tops[tops.length - 1]!.hash : (passed[passed.length - 1]?.hash ?? items[0]!.hash)
  })
}
// tablet and phone: the chips are a scrolling row, so the current one is slid into the middle of it
// (only the row moves, the page doesn't); on desktop the column has nothing to scroll and this does nothing
const nav = ref<HTMLElement | null>(null)
async function revealCurrent() {
  await nextTick()
  const row = nav.value
  const chip = row?.querySelector<HTMLElement>('[aria-current]')
  if (!row || !chip || row.scrollWidth <= row.clientWidth) return
  const offset = chip.getBoundingClientRect().left - row.getBoundingClientRect().left
  const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  row.scrollTo({ left: row.scrollLeft + offset - (row.clientWidth - chip.offsetWidth) / 2, behavior: smooth ? 'smooth' : 'auto' })
}
watch(current, revealCurrent)

onMounted(() => {
  if (props.page === 'settings') {
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('scrollend', onScrollEnd)
  }
  revealCurrent()
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('scrollend', onScrollEnd)
  cancelAnimationFrame(frame)
})
</script>