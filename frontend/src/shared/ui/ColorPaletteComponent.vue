<template>
  <div class="color-palette" :class="`v-${variant}`" role="radiogroup" :aria-label="label" @keydown="onKey">
    <button
      v-for="(color, idx) in choices"
      :key="color ?? 'none'"
      :ref="(el) => bindRef(el, idx)"
      type="button"
      role="radio"
      :aria-checked="selected === color"
      :aria-label="titleFor(color)"
      :tabindex="activeIndex === idx ? 0 : -1"
      :data-color="color ?? 'none'"
      :title="titleFor(color)"
      class="color-swatch"
      @click="select(color, idx)"
    />
  </div>
</template>

<script setup lang="ts">
import { type ComponentPublicInstance, computed, nextTick, ref } from 'vue'
import { PALETTE_COLORS, type PaletteColor } from '@/api/types'

type Variant = 'category' | 'status'
type Choice = PaletteColor | null

const props = withDefaults(
  defineProps<{
    label: string
    variant?: Variant
    clearable?: boolean
  }>(),
  { variant: 'category', clearable: true },
)

const selected = defineModel<Choice>({ default: null })

const choices = computed<Choice[]>(() => (props.clearable ? [null, ...PALETTE_COLORS] : [...PALETTE_COLORS]))

// Human-readable titles for the swatches; palette slugs match PALETTE_COLORS.
const LABELS: Record<PaletteColor, string> = {
  red: 'Красный',
  orange: 'Оранжевый',
  yellow: 'Жёлтый',
  green: 'Зелёный',
  blue: 'Синий',
  pink: 'Розовый',
}
const titleFor = (c: Choice) => (c === null ? 'Без цвета' : LABELS[c])

const refs = ref<HTMLButtonElement[]>([])
// a function ref gets the element, or a component instance in general; only the swatch buttons matter here
function bindRef(el: Element | ComponentPublicInstance | null, idx: number) {
  if (el instanceof HTMLButtonElement) refs.value[idx] = el
}

// WAI-ARIA radiogroup: exactly one swatch is tab-reachable at a time; arrow keys
// move both selection and focus between swatches inside the group.
const activeIndex = computed(() => {
  const i = choices.value.indexOf(selected.value)
  return i === -1 ? 0 : i
})

function select(c: Choice, idx: number) {
  selected.value = c
  nextTick(() => refs.value[idx]?.focus())
}

function onKey(e: KeyboardEvent) {
  const list = choices.value
  const i = activeIndex.value
  let next = i
  switch (e.key) {
    case 'ArrowRight':
    case 'ArrowDown': next = (i + 1) % list.length; break
    case 'ArrowLeft':
    case 'ArrowUp': next = (i - 1 + list.length) % list.length; break
    case 'Home': next = 0; break
    case 'End': next = list.length - 1; break
    default: return
  }
  e.preventDefault()
  // `next` is always inside the list: the switch above wraps it around
  select(list[next]!, next)
}
</script>
