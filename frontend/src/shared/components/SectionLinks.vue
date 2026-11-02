<template>
  <div class="section-links">
    <a
      v-for="item in sections"
      :key="item.hash"
      :href="`${path}${item.hash}`"
      class="section-links-item"
      :aria-current="active && current === item.hash ? 'location' : undefined"
      :tabindex="active ? undefined : -1"
      @click.prevent="pick(item.hash)"
    >
      {{ item.label }}
    </a>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export interface SectionLink {
  hash: string
  label: string
}

// A page's sections under its item in the side menu (AppSidebar). On the current page they're anchors and the
// section at the top of the screen is marked, like the active section on the board; from another page a click
// opens that page at the section (the page scrolls to its hash once loaded).
const props = defineProps<{
  /** the page these sections belong to */
  path: string
  sections: SectionLink[]
  /** this page is open: follow the scroll and jump in place */
  active: boolean
}>()
const route = useRoute()
const router = useRouter()

const current = ref(props.sections.some((i) => i.hash === route.hash) ? route.hash : (props.sections[0]?.hash ?? ''))

// A jump (click, or arriving with #widget) marks that section and holds it while the page scrolls there.
// Plain scrolling marks the last section whose top has passed the upper third of the screen; at the very
// bottom the short last sections can't get that high, so the chosen one stays if it's on screen.
// The click scrolls smoothly instead of the anchor's instant jump; the hold ends when the scroll does
// (the timer is a fallback for browsers without scrollend).
let holdUntil = route.hash ? Date.now() + 1200 : 0
function pick(hash: string) {
  current.value = hash
  if (!props.active) {
    void router.push({ path: props.path, hash })
    return
  }
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
  if (!props.active) return
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(() => {
    if (Date.now() < holdUntil) return
    const tops = props.sections
      .map((i) => ({ hash: i.hash, top: document.querySelector(i.hash)?.getBoundingClientRect().top }))
      .filter((s): s is { hash: string; top: number } => s.top !== undefined)
    if (!tops.length) return
    const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2
    const chosen = tops.find((s) => s.hash === current.value)
    if (atBottom && chosen && chosen.top < window.innerHeight) return
    const passed = tops.filter((s) => s.top <= window.innerHeight / 3)
    current.value = atBottom ? tops[tops.length - 1]!.hash : (passed[passed.length - 1]?.hash ?? tops[0]!.hash)
  })
}
// opening the page starts from its first section (or the one in the address)
watch(() => props.active, (on) => {
  if (on) current.value = props.sections.some((i) => i.hash === route.hash) ? route.hash : (props.sections[0]?.hash ?? '')
})

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('scrollend', onScrollEnd)
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('scrollend', onScrollEnd)
  cancelAnimationFrame(frame)
})
</script>