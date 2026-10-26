<template>
  <section class="board-settings-section board-embed-section widget-color">
    <div class="board-embed-section-head">
      <h2>Цвет виджета</h2>
      <p class="note">Основной цвет кнопки и акцентов в форме. Меняется сразу на всех сайтах, код вставлять заново не нужно.</p>
    </div>

    <!-- one set of controls laid out by grid areas: a stacked form on desktop, two compact rows on a phone -->
    <form class="widget-color-form" novalidate @submit.prevent="save">
      <InputComponent
        v-model="input"
        label="HEX-код"
        hint="например #1d8547"
        type="text"
        maxlength="16"
        autocapitalize="off"
        spellcheck="false"
        :placeholder="DEFAULT_WIDGET_COLOR"
        :error="error"
        @input="error = ''"
      />
      <button class="btn btn-primary widget-color-save" type="submit" :disabled="!changed || saving" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>

      <!-- what visitors will see: the launcher tag and a selected chip, painted with the same tokens as the widget -->
      <div class="widget-color-preview" :style="palette" aria-hidden="true">
        <span class="widget-color-launcher slant">
          <AppIcon name="chat" :size="14" />Обратная связь
        </span>
        <span class="widget-color-chip">Идея</span>
        <span class="widget-color-link">Доска ↗</span>
      </div>
      <p v-if="darkText" class="note widget-color-note">Цвет светлый, поэтому надпись на кнопке будет тёмной: так её легче прочитать.</p>

      <button v-if="saved" type="button" class="link-btn widget-color-reset" :disabled="saving" @click="reset">Вернуть стандартный</button>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { BoardsApi } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import { DEFAULT_WIDGET_COLOR, normalizeHex, textOn, widgetPalette } from '@/shared/utils/widgetColor'

// The widget's main colour on the «Подключение» page. Stored on the board (PATCH /boards/{slug}),
// the widget reads it with the project; the preview uses the same palette function as the widget.
const props = defineProps<{
  slug: string
  // «#rrggbb» or null for the service's green
  color: string | null
}>()
const emit = defineEmits<{
  saved: [color: string | null]
}>()
const notification = useNotificationStore()

const saved = ref(props.color)
const input = ref(props.color ?? '')
const error = ref('')
const saving = ref(false)

watch(() => props.color, (next) => {
  saved.value = next
  input.value = next ?? ''
})

// an empty field means the default; a half-typed value keeps the last good preview instead of flashing green
const parsed = computed(() => (input.value.trim() === '' ? null : normalizeHex(input.value)))
const lastValid = ref(props.color)
watch(parsed, (next) => {
  if (next !== null || input.value.trim() === '') lastValid.value = next
})
const palette = computed(() => widgetPalette(lastValid.value))
const darkText = computed(() => textOn(lastValid.value ?? DEFAULT_WIDGET_COLOR) !== '#ffffff')
const changed = computed(() => (input.value.trim() === '' ? saved.value !== null : parsed.value !== saved.value))

async function save() {
  if (input.value.trim() !== '' && parsed.value === null) {
    error.value = 'Цвет — HEX из 6 или 3 цифр, например #1d8547'
    return
  }
  await send(parsed.value)
}

async function reset() {
  await send(null)
}

async function send(color: string | null) {
  saving.value = true
  try {
    const res = await BoardsApi.update(props.slug, { widget_color: color })
    saved.value = res.data.widget_color
    input.value = res.data.widget_color ?? ''
    emit('saved', res.data.widget_color)
    notification.success(res.data.widget_color ? 'Цвет виджета сохранён' : 'Вернули стандартный цвет')
  } catch (e) {
    const field = e instanceof ApiError ? e.field('widget_color') : null
    if (field) error.value = field
    else notification.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>