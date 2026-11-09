<template>
  <section class="board-settings-section board-access-section widget-color">
    <div class="board-access-section-head">
      <h2>Цвет виджета</h2>
      <p class="note">Основной цвет кнопки и акцентов в форме. Меняется сразу на всех сайтах, код вставлять заново не нужно.</p>
    </div>

    <!-- one set of controls laid out by grid areas: a stacked form on desktop, two compact rows on a phone -->
    <form class="widget-color-form" novalidate @submit.prevent="save">
      <div class="widget-color-picker">
        <div ref="pickerRoot" class="widget-color-dropdown" @keydown.esc.stop.prevent="closePicker(true)">
          <button ref="pickerTrigger" class="btn widget-color-trigger" type="button" :aria-expanded="pickerOpen" :aria-controls="paletteId" @click="pickerOpen = !pickerOpen">
            <span class="widget-color-swatch" :style="palette" aria-hidden="true" />
            <span class="widget-color-label-wide">Выбрать цвет</span>
            <span class="widget-color-label-compact">Палитра</span>
            <AppIcon name="chevron" />
          </button>
          <Transition name="widget-palette">
            <div v-if="pickerOpen" :id="paletteId" class="widget-color-palette" role="region" aria-label="Палитра цвета виджета">
              <ColorPicker
                v-model:pure-color="input"
                is-widget
                format="hex"
                use-type="pure"
                picker-type="chrome"
                :disable-alpha="true"
                :disable-history="true"
              />
            </div>
          </Transition>
        </div>
        <InputComponent
          v-model="hexInput"
          label="HEX"
          placeholder="#1D8547"
          maxlength="7"
          spellcheck="false"
          autocomplete="off"
          :error="hexError"
          @blur="validateHex"
          @input="hexError = ''"
        />
      </div>
      <button class="btn btn-primary widget-color-save" type="submit" :disabled="!changed || saving || !validHex" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>

      <span class="widget-color-preview-label">Предпросмотр</span>

      <!-- what visitors will see: the launcher tag and a selected chip, painted with the same tokens as the widget -->
      <div class="widget-color-preview" :style="palette" aria-hidden="true">
        <span class="widget-color-launcher slant">
          <AppIcon name="chat" :size="14" />Обратная связь
        </span>
        <span class="widget-color-chip">Идея</span>
        <span class="widget-color-link">Доска ↗</span>
      </div>
      <p v-if="darkText" class="note widget-color-note">Цвет светлый, поэтому надпись на кнопке будет тёмной: так её легче прочитать.</p>

      <button type="button" class="link-btn widget-color-reset" :disabled="saving || (!saved && validHex === DEFAULT_WIDGET_COLOR)" @click="reset" aria-label="Вернуть базовый зелёный">
        <span class="widget-color-label-wide">Вернуть базовый зелёный</span>
        <span class="widget-color-label-compact">Базовый цвет</span>
      </button>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import { ColorPicker } from 'vue3-colorpicker'
import 'vue3-colorpicker/style.css'
import { BoardsApi } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import { DEFAULT_WIDGET_COLOR, normalizeHex, textOn, widgetPalette } from '@/shared/utils/widgetColor'

// The widget's main colour on the «Доступ» page (open boards). Stored on the board (PATCH /boards/{slug}),
// the widget reads it with the project; the preview uses the same palette function as the widget.
const props = defineProps<{
  slug: string
  // «#rrggbb» or null for the service's green
  color: string | null
}>()
const emit = defineEmits<{
  saved: [color: string | null]
  preview: [color: string]
}>()
const notification = useNotificationStore()

// saved — то, что в БД (null — сохранён дефолт, hex — кастомный); input — то, что в пикере (всегда hex,
// т.к. ColorPicker не умеет "пусто"). При saved=null подставляем в input дефолтный цвет сервиса.
const saved = ref(props.color)
const input = ref(props.color ?? DEFAULT_WIDGET_COLOR)
const saving = ref(false)
const pickerOpen = ref(false)
const pickerRoot = ref<HTMLElement | null>(null)
const pickerTrigger = ref<HTMLButtonElement | null>(null)
const paletteId = useId()

function closePicker(refocus = false) {
  pickerOpen.value = false
  if (refocus) pickerTrigger.value?.focus()
}
function onPickerOutside(event: PointerEvent) {
  if (!pickerRoot.value?.contains(event.target as Node)) closePicker()
}
watch(pickerOpen, (open) => {
  if (open) document.addEventListener('pointerdown', onPickerOutside, true)
  else document.removeEventListener('pointerdown', onPickerOutside, true)
})
onBeforeUnmount(() => document.removeEventListener('pointerdown', onPickerOutside, true))
const hexInput = ref(input.value.toUpperCase())
const hexError = ref('')
const validHex = computed(() => normalizeHex(hexInput.value))

watch(hexInput, (next) => {
  const color = normalizeHex(next)
  if (color) input.value = color
})
watch(input, (next) => {
  if (normalizeHex(hexInput.value) !== normalizeHex(next)) {
    hexInput.value = next.toUpperCase()
  }
  hexError.value = ''
})

function validateHex() {
  if (!validHex.value) {
    hexError.value = 'Введите HEX: например, #1D8547 или #FFF'
  }
}

watch(() => props.color, (next) => {
  saved.value = next
  input.value = next ?? DEFAULT_WIDGET_COLOR
  hexInput.value = input.value.toUpperCase()
  hexError.value = ''
})

// ColorPicker гарантирует валидный hex, но на всякий случай пропускаем через normalizeHex (lowercase).
const parsed = computed(() => normalizeHex(input.value) ?? DEFAULT_WIDGET_COLOR)
watch(parsed, (color) => emit('preview', color), { immediate: true })
const palette = computed(() => widgetPalette(parsed.value))
const darkText = computed(() => textOn(parsed.value) !== '#ffffff')

// если пользователь руками выбрал дефолтный цвет — считаем это "вернуть дефолт", сохраним null
const toSave = computed(() => (parsed.value === DEFAULT_WIDGET_COLOR ? null : parsed.value))
const changed = computed(() => toSave.value !== saved.value)

async function save() {
  validateHex()
  if (!validHex.value) return
  await send(toSave.value)
}

async function reset() {
  await send(null)
}

async function send(color: string | null) {
  saving.value = true
  try {
    const res = await BoardsApi.update(props.slug, { widget_color: color })
    saved.value = res.data.widget_color
    input.value = res.data.widget_color ?? DEFAULT_WIDGET_COLOR
    hexInput.value = input.value.toUpperCase()
    hexError.value = ''
    emit('saved', res.data.widget_color)
    notification.success(res.data.widget_color ? 'Цвет виджета сохранён' : 'Вернули стандартный цвет')
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>
