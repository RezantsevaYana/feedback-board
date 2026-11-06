<template>
  <div class="onboarding-step">
    <h1>Кто увидит доску?</h1>
    <p class="onboarding-lead">Это можно поменять в любой момент в настройках.</p>
    <RadioCardsComponent v-model="choice" name="onboarding-visibility" :options="options" label="Режим доступа" />

    <!-- the other way: leave the board as it was created and set it up later in the settings -->
    <div class="onboarding-later-box">
      <div>
        <b>Настроить потом</b>
        <p class="note">Доска останется открытой, без доменов для виджета, с категориями «Баг» и «Идея» и статусами «Новое», «Готово», «Отклонено». Всё это можно поменять в настройках.</p>
      </div>
      <button type="button" class="btn" @click="emit('skipAll')">Пропустить настройку</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { BoardsApi, type BoardVisibility } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import RadioCardsComponent from '@/shared/ui/RadioCardsComponent.vue'

// Onboarding step 2: open or closed — or set the board up later. A new board has no posts and no people yet,
// so the switch needs none of the warnings of «Доступ»; it's saved on «Дальше». «Пропустить настройку» leaves the
// defaults the board was created with (open, no domains, «Баг» / «Идея», the three base statuses).
const props = defineProps<{
  slug: string
  visibility: BoardVisibility
}>()
const emit = defineEmits<{
  saved: [visibility: BoardVisibility]
  // every click, before saving: the preview shows the pick at once
  pick: [visibility: BoardVisibility]
  // «Пропустить настройку»: the board keeps its defaults, the onboarding ends
  skipAll: []
}>()
const auth = useAuthStore()
const notification = useNotificationStore()

const options: { value: BoardVisibility; label: string; hint: string }[] = [
  { value: 'public', label: 'Открытая', hint: 'Любой по ссылке читает, голосует и предлагает. Подходит, чтобы собирать отзывы клиентов, в том числе через виджет на сайте.' },
  { value: 'private', label: 'Закрытая', hint: 'Только те, кого вы пригласите. Подходит для команды или тестовой группы.' },
]
const choice = ref<BoardVisibility>(props.visibility)
watch(choice, (value) => emit('pick', value))

async function submit(): Promise<boolean> {
  if (choice.value === props.visibility) return true
  try {
    await BoardsApi.update(props.slug, { visibility: choice.value })
    auth.setVisibility(props.slug, choice.value)
    emit('saved', choice.value)
    return true
  } catch (e) {
    notification.error(errorMessage(e))
    return false
  }
}

defineExpose({ submit })
</script>
