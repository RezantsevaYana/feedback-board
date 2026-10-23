<template>
  <nav class="settings-nav" aria-label="Разделы настроек">
    <a
      v-for="item in items"
      :key="item.hash"
      :href="item.hash"
      class="settings-nav-item"
      :class="{ danger: item.danger }"
      :aria-current="current === item.hash ? 'location' : undefined"
      @click="pick(item.hash)"
    >
      {{ item.label }}
    </a>
    <RouterLink :to="`/app/boards/${slug}/embed`" class="settings-nav-item">Как подключить →</RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

// Anchors to the sections of BoardSettingsPage; "how to connect" is its own page.
// The section at the top of the screen is marked current, like the active section on the board.
defineProps<{
  slug: string
}>()
const route = useRoute()

const items = [
  { hash: '#general', label: 'Общее' },
  { hash: '#categories', label: 'Категории' },
  { hash: '#statuses', label: 'Столбцы' },
  { hash: '#domains', label: 'Домены' },
  { hash: '#delete', label: 'Удалить доску', danger: true },
]
const current = ref(items.some((i) => i.hash === route.hash) ? route.hash : '#general')

// A jump (click, or arriving with #domains) marks that section and holds it while the page scrolls there.
// Plain scrolling marks the last section whose top has passed the upper third of the screen; at the very
// bottom the short last sections can't get that high, so the chosen one stays if it's on screen.
let holdUntil = route.hash ? Date.now() + 1200 : 0
function pick(hash: string) {
  current.value = hash
  holdUntil = Date.now() + 800
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
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  cancelAnimationFrame(frame)
})
</script>