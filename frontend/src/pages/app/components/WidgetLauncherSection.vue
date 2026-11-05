<template>
  <section class="board-settings-section board-access-section widget-launcher">
    <div class="board-access-section-head">
      <h2>Текст кнопки виджета</h2>
      <p class="note">То, что увидит посетитель сайта. По умолчанию — «Обратная связь». Меняется сразу на всех сайтах, код вставлять заново не нужно.</p>
    </div>

    <form class="widget-launcher-form" novalidate @submit.prevent="save">
      <InputComponent
        v-model="input"
        label="Текст на кнопке"
        hint="до 40 символов"
        type="text"
        maxlength="40"
        placeholder="Обратная связь"
        :error="error"
        @input="error = ''"
      />
      <button class="btn btn-primary widget-launcher-save" type="submit" :disabled="!changed || saving" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>

      <!-- preview of the launcher as it will appear on the client's site -->
      <div class="widget-launcher-preview" aria-hidden="true">
        <span class="widget-launcher-preview-label">Предпросмотр</span>
        <span class="btn-primary widget-color-launcher slant">
          <AppIcon name="chat" :size="14" /><span class="widget-launcher-preview-text">{{ input.trim() || 'Обратная связь' }}</span>
        </span>
      </div>

      <button v-if="saved" type="button" class="link-btn widget-launcher-reset" :disabled="saving" @click="reset">Вернуть стандартный</button>
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

// The launcher button's custom label on the «Доступ» page (open boards). Stored on the board (PATCH
// /boards/{slug}), the widget reads it with the project and falls back to «Обратная связь» if empty.
const props = defineProps<{
  slug: string
  text: string | null
}>()
const emit = defineEmits<{
  saved: [text: string | null]
}>()
const notification = useNotificationStore()

const saved = ref(props.text)
const input = ref(props.text ?? '')
const error = ref('')
const saving = ref(false)

watch(() => props.text, (next) => {
  saved.value = next
  input.value = next ?? ''
})

// empty field = default; otherwise store the trimmed value
const changed = computed(() => {
  const trimmed = input.value.trim()
  return (trimmed === '' ? saved.value !== null : trimmed !== saved.value)
})

async function save() {
  await send(input.value.trim() === '' ? null : input.value.trim())
}

async function reset() {
  await send(null)
}

async function send(text: string | null) {
  saving.value = true
  try {
    const res = await BoardsApi.update(props.slug, { widget_launcher_text: text })
    saved.value = res.data.widget_launcher_text
    input.value = res.data.widget_launcher_text ?? ''
    emit('saved', res.data.widget_launcher_text)
    notification.success(res.data.widget_launcher_text ? 'Текст кнопки сохранён' : 'Вернули стандартный текст')
  } catch (e) {
    const field = e instanceof ApiError ? e.field('widget_launcher_text') : null
    if (field) error.value = field
    else notification.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>
