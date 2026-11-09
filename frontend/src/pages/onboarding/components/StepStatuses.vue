<template>
  <div class="onboarding-step">
    <h1>Как предложения будут двигаться?</h1>
    <p class="onboarding-lead">Статусы — колонки доски. Автор видит, где его предложение, и получит письмо, когда оно будет готово.</p>

    <ol class="onboarding-flow" aria-label="Статусы доски по порядку">
      <li v-for="s in board.statuses" :key="s.id">
        <span class="onboarding-chip">
          {{ s.name }}
          <button type="button" class="onboarding-chip-remove" :aria-label="`Убрать статус ${s.name}`" :disabled="busy || board.statuses.length <= 1" @click="deleting = s">×</button>
        </span>
      </li>
    </ol>

    <template v-if="suggestions.length">
      <p class="onboarding-sub">Добавьте ещё один этап — нажмите на подходящий статус</p>
      <div class="onboarding-suggest">
        <button v-for="s in suggestions" :key="s.name" type="button" :disabled="busy" :aria-label="`Добавить статус ${s.name}`" @click="add(s.name, s.color)">
          {{ s.name }}<span class="onboarding-suggest-plus" aria-hidden="true">+</span>
        </button>
      </div>
    </template>

    <form class="onboarding-add" @submit.prevent="addOwn">
      <InputComponent v-model="own" placeholder="Свой статус" aria-label="Свой статус" maxlength="40" />
      <button type="submit" class="btn" :disabled="!own.trim() || busy">Добавить</button>
    </form>
    <p class="note">Переименовать статусы, изменить их порядок и выбрать статус для новых предложений можно в настройках доски.</p>
    <StatusDeleteModal v-if="deleting" :key="deleting.id" :status="deleting" @close="deleting = null" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { errorMessage } from '@/api/client'
import type { PaletteColor, Status } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import StatusDeleteModal from '@/shared/components/StatusDeleteModal.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Statuses are saved at once; the same dialog handles deletion in settings.
const board = useBoardStore()
const notification = useNotificationStore()
const busy = ref(false)
const own = ref('')
const deleting = ref<Status | null>(null)

const SUGGESTED: { name: string; color: PaletteColor }[] = [
  { name: 'В работе', color: 'blue' },
  { name: 'Запланировано', color: 'yellow' },
  { name: 'На проверке', color: 'orange' },
  { name: 'Обсуждаем', color: 'pink' },
]
const suggestions = computed(() => {
  const taken = new Set(board.statuses.map((s) => s.name.trim().toLowerCase()))
  return SUGGESTED.filter((s) => !taken.has(s.name.toLowerCase()))
})

async function run(action: () => Promise<void>) {
  busy.value = true
  try {
    await action()
  } catch (e) {
    notification.error(errorMessage(e))
  } finally {
    busy.value = false
  }
}
const add = (name: string, color: PaletteColor | null) => run(() => board.addStatus(name, color))
async function addOwn() {
  const name = own.value.trim()
  if (!name) return
  await add(name, null)
  own.value = ''
}

const submit = async (): Promise<boolean> => true
defineExpose({ submit })
</script>
