<template>
  <div ref="root" class="entity-color" :class="{ 'entity-color-compact': compactTrigger }" :aria-label="label" @keydown.esc.stop.prevent="close(true)">
    <div class="entity-color-dropdown">
      <button ref="trigger" type="button" class="btn entity-color-trigger" :class="{ invalid: !valid }" :aria-label="`Выбрать: ${label.toLowerCase()}`" :aria-expanded="open" :aria-controls="uid" @click="open = !open">
        <span class="entity-color-swatch" :class="{ colorless: selected === null }" :style="swatchStyle" aria-hidden="true" />
        <span class="entity-color-text">Цвет</span>
        <AppIcon name="chevron" :size="12" />
      </button>
      <Transition name="entity-palette">
        <div v-if="open" :id="uid" ref="popup" class="entity-color-popup" role="region" :aria-label="label">
          <!-- on a phone the HEX field and «Без цвета» live here, so the form row keeps only the swatch button -->
          <div v-if="compact" class="entity-color-hex">
            <InputComponent v-model="hex" :aria-label="`${label}: HEX`" placeholder="HEX" maxlength="7" autocomplete="off" spellcheck="false" :error="error" @input="error = ''" @blur="validate" />
            <button v-if="clearable" type="button" class="btn entity-color-clear" :disabled="selected === null && !hex" aria-label="Без цвета" title="Без цвета" @click="clear"><AppIcon name="close" :size="14" /></button>
          </div>
          <ColorPicker :pure-color="pickerColor" @update:pure-color="pickCustom" is-widget format="hex" use-type="pure" picker-type="chrome" :disable-alpha="true" :disable-history="true" />
        </div>
      </Transition>
    </div>
    <template v-if="!compact">
      <InputComponent v-model="hex" :aria-label="`${label}: HEX`" placeholder="HEX" maxlength="7" autocomplete="off" spellcheck="false" :error="error" @input="error = ''" @blur="validate" />
      <button v-if="clearable" type="button" class="btn entity-color-clear" :disabled="selected === null && !hex" aria-label="Без цвета" title="Без цвета" @click="clear"><AppIcon name="close" :size="14" /></button>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import { ColorPicker } from 'vue3-colorpicker'
import 'vue3-colorpicker/style.css'
import type { PaletteColor } from '@/api/types'
import { useMediaQuery } from '@/shared/composables/useMediaQuery'
import { entityColorHex } from '@/shared/utils/entityColor'
import { DEFAULT_WIDGET_COLOR, normalizeHex } from '@/shared/utils/widgetColor'
import AppIcon from './AppIcon.vue'
import InputComponent from './InputComponent.vue'

const props = withDefaults(defineProps<{
  label: string
  variant?: 'category' | 'status'
  compactTrigger?: boolean
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
const popup = ref<HTMLElement | null>(null)
const uid = useId()
// same as $mobile in scss/utils/_variables.scss
const mobile = useMediaQuery('(max-width: 767px)')
const compact = computed(() => mobile.value || !!props.compactTrigger)
const swatchStyle = computed(() => {
  const color = entityColorHex(selected.value, props.variant)
  return color ? { backgroundColor: color } : {}
})
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
// the add form sits at the very bottom of the «Быстрая настройка» sheet / the settings page:
// scroll the nearest scroll container just enough to show the whole popup
watch(popup, (el) => {
  if (!el) return
  const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
  el.scrollIntoView({ block: 'nearest', behavior: smooth ? 'smooth' : 'auto' })
})
watch(open, (value) => {
  if (value) document.addEventListener('pointerdown', outside, true)
  else document.removeEventListener('pointerdown', outside, true)
})
onBeforeUnmount(() => document.removeEventListener('pointerdown', outside, true))
</script>
