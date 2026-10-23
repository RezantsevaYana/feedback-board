<template>
  <form class="settings-general" novalidate @submit.prevent="save">
    <InputComponent
      v-model="name"
      label="Название доски"
      hint="пользователи увидят его в шапке"
      type="text"
      maxlength="80"
      :error="error"
      @input="error = ''"
    />
    <InputComponent :model-value="settings.slug" label="Адрес доски" hint="не меняется" type="text" readonly />
    <p class="note">
      Адрес нельзя поменять после создания: по нему работают подключённые виджеты и ссылки на доску.
    </p>
    <div class="settings-actions">
      <button class="btn btn-primary" type="submit" :disabled="!changed || saving" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { type BoardSettings, BoardsApi } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Board name; the slug is shown read-only.
const props = defineProps<{
  settings: BoardSettings
}>()
const emit = defineEmits<{
  saved: [settings: BoardSettings]
}>()
const notification = useNotificationStore()

const name = ref(props.settings.name)
const error = ref('')
const saving = ref(false)
const changed = computed(() => name.value.trim() !== props.settings.name)

watch(() => props.settings.name, (next) => (name.value = next))

async function save() {
  if (!name.value.trim()) {
    error.value = 'Назовите доску — чтобы на неё было легко сослаться.'
    return
  }
  saving.value = true
  try {
    const res = await BoardsApi.update(props.settings.slug, { name: name.value.trim() })
    emit('saved', res.data)
    notification.success('Название сохранено')
  } catch (e) {
    const field = e instanceof ApiError ? e.field('name') : null
    if (field) error.value = field
    else notification.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>