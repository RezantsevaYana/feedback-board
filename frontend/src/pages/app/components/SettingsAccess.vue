<template>
  <form class="settings-access" novalidate @submit.prevent="save">
    <SelectComponent
      v-model="choice"
      label="Доступ к доске"
      :options="options"
      :disabled="lockedPrivate"
    />
    <p v-if="lockedPrivate" class="note">
      Доска закрытая. Открыть её обратно нельзя — это ограничение первой версии.
    </p>
    <p v-else-if="choice === 'private' && settings.visibility === 'public'" class="note note-warning">
      После перехода доска будет доступна только участникам. Гостевые голоса на ней останутся,
      но их авторов (анонимные) никто не увидит. Вернуть обратно в открытый режим нельзя.
    </p>
    <p v-else class="note">
      Открытая — любой с ссылкой может читать, голосовать и предлагать.
      Закрытая — только участники из раздела «Участники».
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
import { type BoardSettings, type BoardVisibility, BoardsApi } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import SelectComponent from '@/shared/ui/SelectComponent.vue'

// Visibility toggle for the board. One-way in V1 — if the board is already private, the
// select is locked; moving a public board to private is behind a confirmation (data stays,
// but anonymous votes become visibly anonymous forever).
const props = defineProps<{
  settings: BoardSettings
}>()
const emit = defineEmits<{
  saved: [settings: BoardSettings]
}>()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const options: { value: BoardVisibility; label: string }[] = [
  { value: 'public', label: 'Открытая — любой по ссылке' },
  { value: 'private', label: 'Закрытая — только участники' },
]

const choice = ref<BoardVisibility>(props.settings.visibility)
const saving = ref(false)

const changed = computed(() => choice.value !== props.settings.visibility)
const lockedPrivate = computed(() => props.settings.visibility === 'private')

watch(() => props.settings.visibility, (next) => (choice.value = next))

async function save() {
  if (!changed.value || saving.value) return
  // public → private is behind a confirmation; the arrow doesn't have a return trip in V1
  if (choice.value === 'private' && props.settings.visibility === 'public') {
    const ok = await confirm.ask({
      title: 'Сделать доску закрытой?',
      message: 'Доска станет доступна только участникам, вернуть обратно в открытый режим нельзя. Продолжить?',
      confirmText: 'Сделать закрытой',
      danger: true,
    })
    if (!ok) {
      choice.value = props.settings.visibility
      return
    }
  }
  saving.value = true
  try {
    const res = await BoardsApi.update(props.settings.slug, { visibility: choice.value })
    emit('saved', res.data)
    notification.success(choice.value === 'private' ? 'Доска стала закрытой' : 'Доска открыта')
  } catch (e) {
    choice.value = props.settings.visibility
    const field = e instanceof ApiError ? e.field('visibility') : null
    notification.error(field ?? errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>
