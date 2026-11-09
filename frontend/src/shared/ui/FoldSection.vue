<template>
  <!-- a native <details>: keyboard and screen readers get the folding for free -->
  <details :id="id" ref="root" class="fold-section" :class="{ foldable: compact, 'has-hint': hasHint }" :open="open" @toggle="onToggle">
    <summary ref="head" class="fold-section-head" :tabindex="compact ? undefined : -1" @click="onSummaryClick">
      <h2 class="fold-section-title">
        <span class="fold-section-title-text"><slot name="title">{{ title }}</slot></span>
        <span v-if="hasHint" ref="hintAnchor" class="fold-section-hint-anchor">
          <button
            type="button"
            class="fold-section-hint-btn"
            :aria-expanded="hintOpen"
            :aria-label="hintAriaLabel || 'Пример на доске'"
            @click.prevent.stop="toggleHint"
            @keydown.esc.stop="closeHint"
          >?</button>
          <span class="fold-section-hint-popover" :class="{ open: hintOpen }" role="tooltip"><slot name="hint" /></span>
        </span>
      </h2>
      <!-- folded: what's inside in one line, so there's no need to open it just to see the state -->
      <span v-if="compact && !expanded && ($slots.summary || summary)" class="fold-section-summary"><slot name="summary">{{ summary }}</slot></span>
      <span v-if="compact" class="fold-section-chevron" aria-hidden="true"><AppIcon name="chevron" :size="18" /></span>
    </summary>
    <div class="fold-section-body">
      <slot />
    </div>
  </details>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useSlots, watch } from 'vue'
import { useMediaQuery } from '@/shared/composables/useMediaQuery'
import AppIcon from './AppIcon.vue'

// A settings block that folds on a tablet / phone (TEST.md п. 42, prototype frontend/demo/settings-rework-prototype.html):
// folded by default with the state in its header («2 человека · 1 ждёт приглашения»), a round chevron on the title
// row. On a wide screen it's always open and reads as a plain section with a heading.
// A `#hint` slot (п. 33) adds a «?» next to the title that opens a small example of where the thing shows on the
// board — desktop as a hover popover, phone as an inline block below the title (nothing in the page jumps).
const props = defineProps<{
  title?: string
  /** one line of state shown while folded; the `summary` slot takes rich content (tags) instead */
  summary?: string
  id?: string
  /** start open on a phone too — for what waits for a decision */
  initiallyOpen?: boolean
  /** aria-label on the «?» button when the `#hint` slot is used */
  hintAriaLabel?: string
}>()

const slots = useSlots()
const hasHint = computed(() => !!slots.hint)

// same as $tablet in scss/utils/_variables.scss: the side menu is behind «Меню» from there down
const compact = useMediaQuery('(max-width: 1023px)')
// arriving with its anchor (/structure#statuses, a link from a letter) opens it
const linkedTo = () => !!props.id && window.location.hash === `#${props.id}`
const open = ref(!compact.value || props.initiallyOpen || linkedTo())
// what the header shows: «expanded» flips first, the <details> «open» last when folding (the body stays while it shrinks)
const expanded = ref(open.value)
watch(compact, (isCompact) => {
  open.value = !isCompact || props.initiallyOpen || linkedTo()
  expanded.value = open.value
})

const root = ref<HTMLDetailsElement | null>(null)
const head = ref<HTMLElement | null>(null)
const hintAnchor = ref<HTMLElement | null>(null)
let running: Animation | null = null

// «?» click opens an example — independent of the fold itself
const hintOpen = ref(false)
function toggleHint() {
  if (hintOpen.value) closeHint()
  else openHint()
}
function openHint() {
  hintOpen.value = true
  document.addEventListener('pointerdown', onOutsideHint, true)
}
function closeHint() {
  if (!hintOpen.value) return
  hintOpen.value = false
  document.removeEventListener('pointerdown', onOutsideHint, true)
}
function onOutsideHint(e: PointerEvent) {
  if (!hintAnchor.value?.contains(e.target as Node)) closeHint()
}
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutsideHint, true))

function onToggle(event: Event) {
  // keyboard / find-in-page may toggle it without our click: keep the state in step
  open.value = (event.target as HTMLDetailsElement).open
  if (!running) expanded.value = open.value
}

// the whole block's height animates — the body growing in and the summary line leaving the header at once,
// so nothing under the block jumps; folding shrinks it back to the header before the body goes
function onSummaryClick(event: MouseEvent) {
  event.preventDefault()
  // on a wide screen the heading isn't a switch
  if (!compact.value || !root.value) return
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    open.value = !open.value
    expanded.value = open.value
    return
  }
  void (expanded.value ? fold() : unfold())
}

function animateHeight(from: number, to: number) {
  running?.cancel()
  const el = root.value!
  el.style.overflow = 'hidden'
  running = el.animate({ height: [`${from}px`, `${to}px`] }, { duration: 260, easing: 'cubic-bezier(0.2, 0.9, 0.3, 1)' })
  return running.finished.finally(() => {
    el.style.overflow = ''
    running = null
  })
}

async function unfold() {
  const from = root.value!.offsetHeight
  open.value = true
  expanded.value = true
  await nextTick()
  await animateHeight(from, root.value!.offsetHeight).catch(() => undefined)
}

async function fold() {
  const from = root.value!.offsetHeight
  expanded.value = false
  await nextTick()
  // folded = the header alone (now with its summary line back) plus the block's own border and padding
  const box = getComputedStyle(root.value!)
  const frame = ['borderTopWidth', 'borderBottomWidth', 'paddingTop', 'paddingBottom'].reduce((sum, key) => sum + parseFloat(box[key as 'paddingTop']), 0)
  await animateHeight(from, (head.value?.offsetHeight ?? 0) + frame).catch(() => undefined)
  open.value = false
}
</script>
