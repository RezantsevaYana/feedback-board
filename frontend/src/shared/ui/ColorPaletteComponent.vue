<template>
  <div ref="root" class="entity-color" :aria-label="label" @keydown.esc.stop.prevent="close(true)">
    <div class="entity-color-dropdown">
      <button ref="trigger" type="button" class="btn entity-color-trigger" :aria-label="`Выбрать: ${label.toLowerCase()}`" :aria-expanded="open" :aria-controls="uid" @click="open = !open">
        <span class="entity-color-swatch" :class="{ colorless: selected === null }" :style="swatchStyle" aria-hidden="true" />
        Цвет <AppIcon name="chevron" :size="12" />
      </button>
      <Transition name="entity-palette">
        <div v-if="open" :id="uid" class="entity-color-popup" role="region" :aria-label="label">
          <div class="color-palette" :class="`v-${variant}`" role="group" aria-label="Готовые цвета">
            <button v-for="color in PALETTE_COLORS" :key="color" type="button" class="color-swatch" :data-color="color" :aria-label="LABELS[color]" :title="LABELS[color]" :aria-pressed="selected === color" @click="selectPreset(color)" />
          </div>
          <ColorPicker :pure-color="pickerColor" @update:pure-color="pickCustom" is-widget format="hex" use-type="pure" picker-type="chrome" :disable-alpha="true" :disable-history="true" />
        </div>
      </Transition>
    </div>
    <InputComponent v-model="hex" :aria-label="`${label}: HEX`" placeholder="HEX" maxlength="7" autocomplete="off" spellcheck="false" :error="error" @input="error = ''" @blur="validate" />
    <button v-if="clearable" type="button" class="btn entity-color-clear" :disabled="selected === null && !hex" aria-label="Без цвета" title="Без цвета" @click="clear"><AppIcon name="close" :size="14" /></button>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import { ColorPicker } from 'vue3-colorpicker'
import 'vue3-colorpicker/style.css'
import { PALETTE_COLORS, type PaletteColor } from '@/api/types'
import { entityColorHex } from '@/shared/utils/entityColor'
import { DEFAULT_WIDGET_COLOR, normalizeHex } from '@/shared/utils/widgetColor'
import AppIcon from './AppIcon.vue'
import InputComponent from './InputComponent.vue'

const props = withDefaults(defineProps<{
  label: string
  variant?: 'category' | 'status'
  clearable?: boolean
}>(), { variant: 'category', clearable: true })
const selected = defineModel<PaletteColor | null>({ default: null })
const valid = defineModel<boolean>('valid', { default: true })
const hex = ref(entityColorHex(selected.value, props.variant).toUpperCase())
const pickerColor = ref(entityColorHex(selected.value, props.variant) || DEFAULT_WIDGET_COLOR)
const error = ref('')
const open = ref(false)
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const uid = useId()
const swatchStyle = computed(() => {
  const color = entityColorHex(selected.value, props.variant)
  return color ? { backgroundColor: color } : {}
})
const LABELS: Record<(typeof PALETTE_COLORS)[number], string> = {
  red: 'Красный', orange: 'Оранжевый', yellow: 'Жёлтый', green: 'Зелёный', blue: 'Синий', pink: 'Розовый',
}

watch(hex, (value) => {
  const normalized = normalizeHex(value)
  valid.value = !!normalized || (props.clearable && value.trim() === '')
  if (normalized) {
    if (normalized !== entityColorHex(selected.value, props.variant)) selected.value = normalized as PaletteColor
    pickerColor.value = normalized
  } else if (value.trim() === '' && props.clearable) selected.value = null
})
watch(selected, (color) => {
  const next = entityColorHex(color, props.variant)
  if (next !== normalizeHex(hex.value)) hex.value = next.toUpperCase()
  pickerColor.value = next || DEFAULT_WIDGET_COLOR
  valid.value = true
  error.value = ''
})
function pickCustom(color: unknown) {
  if (typeof color !== 'string') return
  const next = normalizeHex(color)
  if (next) hex.value = next.toUpperCase()
}

function selectPreset(color: PaletteColor) {
  selected.value = color
  hex.value = entityColorHex(color, props.variant).toUpperCase()
  valid.value = true
  error.value = ''
}
function clear() {
  selected.value = null
  hex.value = ''
  valid.value = true
  error.value = ''
}
function validate() {
  if (!valid.value) error.value = 'Введите HEX, например #1D8547'
}
function close(refocus = false) {
  open.value = false
  if (refocus) trigger.value?.focus()
}
function outside(event: PointerEvent) {
  if (!root.value?.contains(event.target as Node)) close()
}
watch(open, (value) => {
  if (value) document.addEventListener('pointerdown', outside, true)
  else document.removeEventListener('pointerdown', outside, true)
})
onBeforeUnmount(() => document.removeEventListener('pointerdown', outside, true))
</script>
