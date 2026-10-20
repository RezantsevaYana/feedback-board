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
        v-bind="$attrs"
        @click="toggle"
        @keydown="onKey"
      >
        <span class="select-value" :class="{ placeholder: !current }">{{ current?.label ?? '—' }}</span>
        <AppIcon name="chevron" />
      </button>
      <ul
        v-if="open"
        :id="`${uid}-list`"
        class="select-list"
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
          @mousemove="active = i"
          @mousedown.prevent="pick(i)"
        >
          {{ option.label }}
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts" generic="T extends string | number | null">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'

export interface SelectOption<V> {
  value: V
  label: string
}

defineOptions({ inheritAttrs: false })
const model = defineModel<T>({ required: true })
const props = defineProps<{
  options: SelectOption<T>[]
  label?: string
  hint?: string
}>()

const uid = useId()
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const open = ref(false)
const active = ref(-1)

const current = computed(() => props.options.find((o) => o.value === model.value))

function toggle() {
  open.value ? close() : openList()
}
function openList() {
  if (open.value) return
  open.value = true
  active.value = Math.max(0, props.options.findIndex((o) => o.value === model.value))
  document.addEventListener('pointerdown', onOutside, true)
}
function close() {
  if (!open.value) return
  open.value = false
  document.removeEventListener('pointerdown', onOutside, true)
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
function scrollActiveIntoView() {
  root.value?.querySelector<HTMLElement>('.select-option.active')?.scrollIntoView({ block: 'nearest' })
}
function onOutside(e: PointerEvent) {
  if (!root.value?.contains(e.target as Node)) close()
}
function onKey(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') { e.preventDefault(); open.value ? move(1) : openList() }
  else if (e.key === 'ArrowUp') { e.preventDefault(); open.value ? move(-1) : openList() }
  else if (e.key === 'Home' && open.value) { e.preventDefault(); active.value = 0; nextTick(scrollActiveIntoView) }
  else if (e.key === 'End' && open.value) { e.preventDefault(); active.value = props.options.length - 1; nextTick(scrollActiveIntoView) }
  else if (e.key === 'Enter' || (e.key === ' ' && open.value)) { e.preventDefault(); open.value ? pick(active.value) : openList() }
  else if (e.key === 'Escape' && open.value) { e.preventDefault(); close(); trigger.value?.focus() }
  else if (e.key === 'Tab') close()
}

watch(open, (v) => v && nextTick(scrollActiveIntoView))
onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside, true))
</script>
