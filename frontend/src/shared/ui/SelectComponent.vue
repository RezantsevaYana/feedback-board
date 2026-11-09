<template>
  <div class="field">
    <span v-if="label">{{ label }} <small v-if="hint">· {{ hint }}</small></span>
    <div ref="root" class="select" :class="{ open }">
      <button
        :id="`${uid}-trigger`"
        ref="trigger"
        type="button"
        class="select-trigger input"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        :aria-controls="`${uid}-list`"
        :aria-activedescendant="open && active >= 0 ? `${uid}-opt-${active}` : undefined"
        v-bind="$attrs"
        @click="toggle"
        @keydown="onKey"
      >
        <span class="select-value" :class="{ placeholder: !current }">{{ current?.label ?? '—' }}</span>
        <AppIcon name="chevron" />
      </button>
      <Teleport to="body" :disabled="!portal">
        <ul
          v-if="open"
          ref="list"
          :id="`${uid}-list`"
          class="select-list"
          :class="{ 'with-hints': hasHints, up, portaled: portal }"
          :style="portal ? portalStyle : undefined"
          role="listbox"
          tabindex="-1"
          :aria-activedescendant="active >= 0 ? `${uid}-opt-${active}` : undefined"
        >
          <li
            v-for="(option, i) in options"
            :id="`${uid}-opt-${i}`"
            :key="String(option.value)"
            class="select-option"
            :class="{ active: i === active, selected: option.value === model }"
            role="option"
            :aria-selected="option.value === model"
            :title="option.label"
            @mousemove="active = i"
            @mousedown.prevent="pick(i)"
          >
            {{ option.label }}
            <small v-if="option.hint" class="select-option-hint">{{ option.hint }}</small>
          </li>
        </ul>
      </Teleport>
    </div>
  </div>
</template>

<script setup lang="ts" generic="T extends string | number | null">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch, type CSSProperties } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'

export interface SelectOption<V> {
  value: V
  label: string
  /** small grey line under the label in the open list (e.g. what a role may do) */
  hint?: string
}

defineOptions({ inheritAttrs: false })
const model = defineModel<T>({ required: true })
const props = defineProps<{
  options: SelectOption<T>[]
  label?: string
  hint?: string
  /** Keep an overlay list outside scrollable dialogs. */
  portal?: boolean
}>()

const uid = useId()
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const list = ref<HTMLElement | null>(null)
const portalStyle = ref<CSSProperties>({ visibility: 'hidden' })
let portalAnchor: { top: number; left: number } | null = null
const open = ref(false)
const active = ref(-1)
// the list opens upward when there's no room under the field (a select at the bottom of a modal):
// otherwise it would stretch the modal and give it a scrollbar
const up = ref(false)
const LIST_ROOM = 300

function roomAround(): { above: number; below: number } {
  const box = trigger.value?.getBoundingClientRect()
  if (!box) return { above: 0, below: LIST_ROOM }
  // the nearest scrolling ancestor (a modal) bounds the list, or else the screen
  let scroller: HTMLElement | null = root.value?.parentElement ?? null
  while (scroller && !/(auto|scroll)/.test(getComputedStyle(scroller).overflowY)) scroller = scroller.parentElement
  const frame = scroller?.getBoundingClientRect() ?? { top: 0, bottom: window.innerHeight }
  return { above: box.top - frame.top, below: frame.bottom - box.bottom }
}

const current = computed(() => props.options.find((o) => o.value === model.value))
const hasHints = computed(() => props.options.some((o) => o.hint))

function toggle() {
  if (open.value) close()
  else openList()
}
async function openList() {
  if (open.value) return
  trigger.value?.focus({ preventScroll: true })
  const room = roomAround()
  up.value = room.below < LIST_ROOM && room.above > room.below
  open.value = true
  active.value = Math.max(0, props.options.findIndex((o) => o.value === model.value))
  document.addEventListener('pointerdown', onOutside, true)
  if (props.portal) {
    portalStyle.value = { visibility: 'hidden' }
    await nextTick()
    if (!open.value || !root.value) return
    positionPortal()
    window.addEventListener('resize', close)
    document.addEventListener('scroll', onScroll, true)
    window.visualViewport?.addEventListener('resize', close)
  }
}
function close() {
  if (!open.value) return
  open.value = false
  removeListeners()
}
function pick(i: number) {
  const option = props.options[i]
  if (!option) return
  model.value = option.value
  close()
  trigger.value?.focus()
}
function move(step: number) {
  if (!props.options.length) return
  const n = props.options.length
  active.value = active.value < 0 ? (step > 0 ? 0 : n - 1) : (active.value + step + n) % n
  nextTick(scrollActiveIntoView)
}
function positionPortal() {
  const box = trigger.value?.getBoundingClientRect()
  const menu = list.value
  if (!box || !menu || !open.value) return
  portalAnchor = { top: box.top, left: box.left }
  const frame = window.visualViewport
  const top = frame?.offsetTop ?? 0
  const left = frame?.offsetLeft ?? 0
  const width = frame?.width ?? window.innerWidth
  const height = frame?.height ?? window.innerHeight
  const menuWidth = Math.min(Math.max(box.width, hasHints.value ? 340 : box.width), width - 16)
  portalStyle.value = { width: `${menuWidth}px` }
  menu.style.width = `${menuWidth}px`
  const above = Math.max(0, box.top - top - 8)
  const below = Math.max(0, top + height - box.bottom - 8)
  const desired = menu.scrollHeight
  up.value = below < desired && above > below
  const maxHeight = Math.max(0, (up.value ? above : below) - 4)
  portalStyle.value = {
    width: `${menuWidth}px`,
    left: `${Math.max(left + 8, Math.min(box.left, left + width - menuWidth - 8))}px`,
    top: up.value ? 'auto' : `${box.bottom + 4}px`,
    bottom: up.value ? `${window.innerHeight - box.top + 4}px` : 'auto',
    maxHeight: `${maxHeight}px`,
  }
  nextTick(scrollActiveIntoView)
}
function scrollActiveIntoView() {
  const menu = list.value
  const option = menu?.querySelector<HTMLElement>('.select-option.active')
  if (!menu || !option) return
  // Scroll only the options; scrollIntoView also moved the dialog and page.
  const delta = option.getBoundingClientRect().top - menu.getBoundingClientRect().top
  if (delta < 0) menu.scrollTop += delta
  else if (delta + option.offsetHeight > menu.clientHeight) menu.scrollTop += delta + option.offsetHeight - menu.clientHeight
}
function onOutside(e: PointerEvent) {
  if (!root.value?.contains(e.target as Node) && !list.value?.contains(e.target as Node)) close()
}
function onScroll(e: Event) {
  if (list.value?.contains(e.target as Node)) return
  const box = trigger.value?.getBoundingClientRect()
  // Opening an upward list can emit a document scroll event without moving its anchor.
  // Only dismiss when the page actually moves the field; option scrolling stays local.
  if (!box || !portalAnchor || Math.abs(box.top - portalAnchor.top) > 0.5 || Math.abs(box.left - portalAnchor.left) > 0.5) close()
}
function removeListeners() {
  document.removeEventListener('pointerdown', onOutside, true)
  document.removeEventListener('scroll', onScroll, true)
  window.removeEventListener('resize', close)
  window.visualViewport?.removeEventListener('resize', close)
}
function onKey(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') { e.preventDefault(); if (open.value) move(1); else openList() }
  else if (e.key === 'ArrowUp') { e.preventDefault(); if (open.value) move(-1); else openList() }
  else if (e.key === 'Home' && open.value) { e.preventDefault(); active.value = 0; nextTick(scrollActiveIntoView) }
  else if (e.key === 'End' && open.value) { e.preventDefault(); active.value = props.options.length - 1; nextTick(scrollActiveIntoView) }
  else if (e.key === 'Enter' || (e.key === ' ' && open.value)) { e.preventDefault(); if (open.value) pick(active.value); else openList() }
  else if (e.key === 'Escape' && open.value) { e.preventDefault(); e.stopPropagation(); close(); trigger.value?.focus() }
  else if (e.key === 'Tab') close()
}

watch(open, (v) => v && nextTick(scrollActiveIntoView))
onBeforeUnmount(removeListeners)
</script>
