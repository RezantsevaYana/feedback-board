<template>
  <div class="field">
    <span v-if="label">{{ label }} <small v-if="hint">· {{ hint }}</small></span>
    <div ref="root" class="select multi-select" :class="{ open }">
      <button
        :id="`${uid}-trigger`"
        ref="trigger"
        type="button"
        class="select-trigger input"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        :aria-controls="`${uid}-list`"
        v-bind="$attrs"
        @click="toggle"
        @keydown="onKey"
      >
        <!-- invisible copies of every label the trigger can show, stacked in one cell:
             the trigger is as wide as the longest of them and doesn't change width as picks change -->
        <span class="select-value multi-select-value">
          <span>{{ summary }}</span>
          <span v-for="text in sizes" :key="text" class="multi-select-size" aria-hidden="true">{{ text }}</span>
        </span>
        <AppIcon name="chevron" />
      </button>
      <ul
        v-if="open"
        :id="`${uid}-list`"
        class="select-list"
        :class="{ up }"
        role="listbox"
        aria-multiselectable="true"
        tabindex="-1"
        :aria-activedescendant="active >= 0 ? `${uid}-opt-${active}` : undefined"
      >
        <!-- row 0 clears the pick: «all» is the empty selection -->
        <li
          :id="`${uid}-opt-0`"
          class="select-option multi-select-option"
          :class="{ active: active === 0, selected: !model.length }"
          role="option"
          :aria-selected="!model.length"
          @mousemove="active = 0"
          @mousedown.prevent="pick(0)"
        >
          {{ allLabel }}
          <AppIcon name="check" :size="14" class="multi-select-mark" />
        </li>
        <li
          v-for="(option, i) in options"
          :id="`${uid}-opt-${i + 1}`"
          :key="String(option.value)"
          class="select-option multi-select-option"
          :class="{ active: i + 1 === active, selected: model.includes(option.value) }"
          role="option"
          :aria-selected="model.includes(option.value)"
          @mousemove="active = i + 1"
          @mousedown.prevent="pick(i + 1)"
        >
          {{ option.label }}
          <AppIcon name="check" :size="14" class="multi-select-mark" />
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts" generic="T extends string | number">
import { computed, nextTick, onBeforeUnmount, ref, useId } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'

// A select that keeps several values (a filter: «any of these»). Looks and keys like SelectComponent;
// the list stays open while picking; a picked row is tinted with a check on the right. The empty selection means «all» and is the first row (allLabel).
defineOptions({ inheritAttrs: false })
const model = defineModel<T[]>({ required: true })
const props = defineProps<{
  options: { value: T; label: string }[]
  /** text of the empty selection, e.g. «Все статусы» */
  allLabel: string
  /** the trigger text for 2+ picked, e.g. (n) => `${n} статуса` */
  countLabel: (n: number) => string
  label?: string
  hint?: string
}>()

const uid = useId()
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const open = ref(false)
const active = ref(-1)
const up = ref(false)
const LIST_ROOM = 300

// «Все статусы» / «Новое» / «2 статуса»
const summary = computed(() => {
  const picked = props.options.filter((o) => model.value.includes(o.value))
  if (!picked.length) return props.allLabel
  if (picked.length === 1) return picked[0]!.label
  return props.countLabel(picked.length)
})
// everything summary can be — sets the trigger's fixed width
const sizes = computed(() => [
  props.allLabel,
  ...props.options.map((o) => o.label),
  ...props.options.slice(1).map((_, i) => props.countLabel(i + 2)),
])

function roomAround(): { above: number; below: number } {
  const box = trigger.value?.getBoundingClientRect()
  if (!box) return { above: 0, below: LIST_ROOM }
  let scroller: HTMLElement | null = root.value?.parentElement ?? null
  while (scroller && !/(auto|scroll)/.test(getComputedStyle(scroller).overflowY)) scroller = scroller.parentElement
  const frame = scroller?.getBoundingClientRect() ?? { top: 0, bottom: window.innerHeight }
  return { above: box.top - frame.top, below: frame.bottom - box.bottom }
}

function toggle() {
  if (open.value) close()
  else openList()
}
function openList() {
  if (open.value) return
  const room = roomAround()
  up.value = room.below < LIST_ROOM && room.above > room.below
  open.value = true
  active.value = 0
  document.addEventListener('pointerdown', onOutside, true)
}
function close() {
  if (!open.value) return
  open.value = false
  document.removeEventListener('pointerdown', onOutside, true)
}
// a new array every time, so watchers on the filter see the change
function pick(row: number) {
  if (row === 0) {
    model.value = []
    return
  }
  const option = props.options[row - 1]
  if (!option) return
  model.value = model.value.includes(option.value) ? model.value.filter((v) => v !== option.value) : [...model.value, option.value]
}
function move(step: number) {
  const n = props.options.length + 1
  active.value = active.value < 0 ? (step > 0 ? 0 : n - 1) : (active.value + step + n) % n
  nextTick(() => root.value?.querySelector<HTMLElement>('.select-option.active')?.scrollIntoView({ block: 'nearest' }))
}
function onOutside(e: PointerEvent) {
  if (!root.value?.contains(e.target as Node)) close()
}
function onKey(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') { e.preventDefault(); if (open.value) move(1); else openList() }
  else if (e.key === 'ArrowUp') { e.preventDefault(); if (open.value) move(-1); else openList() }
  else if (e.key === 'Enter' || (e.key === ' ' && open.value)) { e.preventDefault(); if (open.value) pick(active.value); else openList() }
  else if (e.key === 'Escape' && open.value) { e.preventDefault(); close(); trigger.value?.focus() }
  else if (e.key === 'Tab') close()
}

onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside, true))
</script>
