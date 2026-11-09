<template>
  <section class="widget-appearance widget-subsection">
    <h3>Внешний вид</h3>
    <form novalidate @submit.prevent="save">
      <fieldset :disabled="saving">
        <legend class="visually-hidden">Внешний вид виджета</legend>
        <div class="widget-appearance-label">Цвет кнопки</div>
        <div class="widget-appearance-colors">
          <div ref="pickerRoot" class="widget-color-dropdown" @keydown.esc.stop.prevent="closePicker(true)">
            <button ref="pickerTrigger" class="btn" type="button" :aria-expanded="pickerOpen" :aria-controls="paletteId" @click="pickerOpen = !pickerOpen">
              <span class="widget-color-swatch" :style="palette" aria-hidden="true" />Цвет<AppIcon name="chevron" />
            </button>
            <Transition name="widget-palette">
              <div v-if="pickerOpen" :id="paletteId" class="widget-color-palette" role="region" aria-label="Палитра цвета виджета">
                <ColorPicker v-model:pure-color="input" is-widget format="hex" use-type="pure" picker-type="chrome" :disable-alpha="true" :disable-history="true" />
              </div>
            </Transition>
          </div>
          <InputComponent v-model="hexInput" aria-label="Цвет в формате HEX" placeholder="#1D8547" maxlength="7" spellcheck="false" autocomplete="off" :error="hexError" @blur="validateHex" @input="hexError = ''" />
          <button class="btn" type="button" :disabled="validHex === DEFAULT_WIDGET_COLOR" @click="reset">Сбросить</button>
        </div>
        <p v-if="darkText" class="note">На светлой кнопке надпись будет тёмной, чтобы её было легче прочитать.</p>
        <InputComponent v-model="text" label="Текст кнопки" hint="до 40 символов" maxlength="40" placeholder="Обратная связь" :error="textError" @input="textError = ''" />
        <div class="widget-appearance-actions">
          <button class="btn btn-primary" type="submit" :disabled="!changed || !validHex || saving" :aria-busy="saving">{{ saving ? 'Сохраняем…' : 'Сохранить' }}</button>
        </div>
      </fieldset>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import { ColorPicker } from 'vue3-colorpicker'
import 'vue3-colorpicker/style.css'
import { BoardsApi } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
import { DEFAULT_WIDGET_COLOR, normalizeHex, textOn, widgetPalette } from '@/shared/utils/widgetColor'

// Shared appearance form: one draft, one preview, one request for colour and label.
const props = defineProps<{
  slug: string
  // «#rrggbb» or null for the service's green
  color: string | null
  text: string | null
}>()
const emit = defineEmits<{
  saved: [value: { color: string | null; text: string | null }]
  preview: [value: { color: string; text: string }]
}>()
const notification = useNotificationStore()

// saved — то, что в БД (null — сохранён дефолт, hex — кастомный); input — то, что в пикере (всегда hex,
// т.к. ColorPicker не умеет "пусто"). При saved=null подставляем в input дефолтный цвет сервиса.
const saved = ref(props.color)
const savedText = ref(props.text)
const text = ref(props.text ?? '')
const textError = ref('')
watch(() => props.text, (next) => { savedText.value = next; text.value = next ?? '' })
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
watch([parsed, text], ([color, text]) => emit('preview', { color, text }), { immediate: true })
const palette = computed(() => widgetPalette(parsed.value))
const darkText = computed(() => textOn(parsed.value) !== '#ffffff')

// если пользователь руками выбрал дефолтный цвет — считаем это "вернуть дефолт", сохраним null
const toSave = computed(() => (parsed.value === DEFAULT_WIDGET_COLOR ? null : parsed.value))
const toSaveText = computed(() => text.value.trim() || null)
const changed = computed(() => toSave.value !== saved.value || toSaveText.value !== savedText.value)

async function save() {
  validateHex()
  if (!validHex.value) return
  await send()
}

function reset() {
  input.value = DEFAULT_WIDGET_COLOR
  hexInput.value = DEFAULT_WIDGET_COLOR.toUpperCase()
  hexError.value = ''
}

async function send() {
  saving.value = true
  textError.value = ''
  try {
    const res = await BoardsApi.update(props.slug, {
      widget_color: toSave.value,
      widget_launcher_text: toSaveText.value,
    })
    saved.value = res.data.widget_color
    savedText.value = res.data.widget_launcher_text
    input.value = saved.value ?? DEFAULT_WIDGET_COLOR
    hexInput.value = input.value.toUpperCase()
    text.value = savedText.value ?? ''
    emit('saved', { color: saved.value, text: savedText.value })
    notification.success('Внешний вид виджета сохранён')
  } catch (e) {
    if (e instanceof ApiError) {
      hexError.value = e.field('widget_color') ?? ''
      textError.value = e.field('widget_launcher_text') ?? ''
    }
    if (!hexError.value && !textError.value) notification.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>
