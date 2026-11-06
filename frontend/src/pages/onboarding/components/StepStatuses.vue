<template>
  <div class="onboarding-step">
    <h1>Как предложения будут двигаться?</h1>
    <p class="onboarding-lead">Статусы — колонки доски. Автор видит, где его предложение, и получит письмо, когда оно будет готово.</p>

    <ol class="onboarding-flow" aria-label="Статусы доски по порядку">
      <li v-for="s in board.statuses" :key="s.id">
        <span v-if="s.is_system" class="pill" :data-color="s.color ?? 'none'" :style="entityColorStyle(s.color)">{{ s.name }}</span>
        <span v-else class="onboarding-chip">
          {{ s.name }}
          <button type="button" class="onboarding-chip-remove" :aria-label="`Убрать статус ${s.name}`" :disabled="busy" @click="remove(s.id)">×</button>
        </span>
      </li>
    </ol>

    <template v-if="suggestions.length">
      <p class="onboarding-sub">Шаг между «Новое» и «Готово» — нажмите, чтобы добавить</p>
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
    <p class="note">«Новое», «Готово» и «Отклонено» есть всегда, их можно переименовать позже в настройках.</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { errorMessage } from '@/api/client'
import type { PaletteColor } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { entityColorStyle } from '@/shared/utils/entityColor'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Onboarding step 4: the columns. The three system ones stay; custom ones land between «Новое» and «Готово»
// (the server places them), removable right here. Saved at once, as in StatusesEditor.
const board = useBoardStore()
const notification = useNotificationStore()
const busy = ref(false)
const own = ref('')

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
const remove = (id: number) => run(() => board.deleteStatus(id))
async function addOwn() {
  const name = own.value.trim()
  if (!name) return
  await add(name, null)
  own.value = ''
}

const submit = async (): Promise<boolean> => true
defineExpose({ submit })
</script>
