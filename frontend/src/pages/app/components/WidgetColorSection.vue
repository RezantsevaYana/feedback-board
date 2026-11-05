<template>
  <section class="board-settings-section board-access-section widget-color">
    <div class="board-access-section-head">
      <h2>Цвет виджета</h2>
      <p class="note">Основной цвет кнопки и акцентов в форме. Меняется сразу на всех сайтах, код вставлять заново не нужно.</p>
    </div>

    <!-- one set of controls laid out by grid areas: a stacked form on desktop, two compact rows on a phone -->
    <form class="widget-color-form" novalidate @submit.prevent="save">
      <div class="widget-color-picker">
        <ColorPicker
          v-model:pure-color="input"
          format="hex"
          use-type="pure"
          picker-type="chrome"
          :disable-alpha="true"
          :disable-history="true"
          round-history
          :z-index="100"
        />
        <!-- HEX is also visible inside the picker's popup; a quick-glance echo stays here for the collapsed state -->
        <code class="widget-color-hex">{{ (input || DEFAULT_WIDGET_COLOR).toUpperCase() }}</code>
      </div>
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
import { ColorPicker } from 'vue3-colorpicker'
import 'vue3-colorpicker/style.css'
import { BoardsApi } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
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
}>()
const notification = useNotificationStore()

// saved — то, что в БД (null — сохранён дефолт, hex — кастомный); input — то, что в пикере (всегда hex,
// т.к. ColorPicker не умеет "пусто"). При saved=null подставляем в input дефолтный цвет сервиса.
const saved = ref(props.color)
const input = ref(props.color ?? DEFAULT_WIDGET_COLOR)
const saving = ref(false)

watch(() => props.color, (next) => {
  saved.value = next
  input.value = next ?? DEFAULT_WIDGET_COLOR
})

// ColorPicker гарантирует валидный hex, но на всякий случай пропускаем через normalizeHex (lowercase).
const parsed = computed(() => normalizeHex(input.value) ?? DEFAULT_WIDGET_COLOR)
const palette = computed(() => widgetPalette(parsed.value))
const darkText = computed(() => textOn(parsed.value) !== '#ffffff')

// если пользователь руками выбрал дефолтный цвет — считаем это "вернуть дефолт", сохраним null
const toSave = computed(() => (parsed.value === DEFAULT_WIDGET_COLOR ? null : parsed.value))
const changed = computed(() => toSave.value !== saved.value)

async function save() {
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
    emit('saved', res.data.widget_color)
    notification.success(res.data.widget_color ? 'Цвет виджета сохранён' : 'Вернули стандартный цвет')
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>