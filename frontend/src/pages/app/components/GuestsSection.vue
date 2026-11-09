<template>
  <FoldSection id="guests" title="Гости" class="board-access-group" :summary="summary">
    <!-- test.md п. 40 — what a guest may do on the open board. Both default to «Все посетители», registration not
         required; the widget on the site hides itself if guests can't post. On a phone the block folds and the
         header reads the current state (prototype frontend/demo/settings-rework-prototype.html). -->
    <RadioGroupComponent
      v-model="postChoice"
      name="guests-can-post"
      legend="Кто может оставлять предложения"
      :options="postOptions"
      :disabled="savingPost"
    />
    <RadioGroupComponent
      v-model="voteChoice"
      name="guests-can-vote"
      legend="Кто может голосовать"
      :options="voteOptions"
      :disabled="savingVote"
    />
  </FoldSection>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { BoardsApi } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import FoldSection from '@/shared/ui/FoldSection.vue'
import RadioGroupComponent from '@/shared/ui/RadioGroupComponent.vue'

// «Доступ» → «Гости» (test.md п. 40): два радиоответа. «Все посетители» (true) и «Только с аккаунтом» (false)
// для создания постов и для голосования — независимо друг от друга. Переключение сохраняется сразу; при ошибке
// возвращаем значение обратно и показываем тост (как у «Поисковики»).
type GuestChoice = 'everyone' | 'account'

const props = defineProps<{
  slug: string
  guestsCanPost: boolean
  guestsCanVote: boolean
}>()
const emit = defineEmits<{
  saved: [{ guestsCanPost: boolean; guestsCanVote: boolean }]
}>()
const notification = useNotificationStore()

const postOptions: { value: GuestChoice; label: string; hint: string }[] = [
  { value: 'everyone', label: 'Все посетители', hint: 'Регистрироваться не нужно — на доске и через виджет на сайте.' },
  { value: 'account', label: 'Только с аккаунтом', hint: 'Посетителя попросят зарегистрироваться или войти. Виджет на сайте скроется: войти в нём нельзя.' },
]
const voteOptions: { value: GuestChoice; label: string; hint: string }[] = [
  { value: 'everyone', label: 'Все посетители', hint: 'Регистрироваться не нужно.' },
  { value: 'account', label: 'Только с аккаунтом', hint: 'Голоса видны всем, а проголосовать можно после регистрации или входа.' },
]

const postChoice = ref<GuestChoice>(props.guestsCanPost ? 'everyone' : 'account')
const voteChoice = ref<GuestChoice>(props.guestsCanVote ? 'everyone' : 'account')
const savingPost = ref(false)
const savingVote = ref(false)

// phone fold header: say the current rules in one line, so nothing is hidden blind
const summary = computed(() => {
  const postPart = props.guestsCanPost ? 'предлагают все' : 'предлагают с аккаунтом'
  const votePart = props.guestsCanVote ? 'голосуют все' : 'голосуют с аккаунтом'
  return `${postPart} · ${votePart}`
})

// react to external changes (e.g. the mode switch reloads settings)
watch(() => props.guestsCanPost, (next) => {
  if (!savingPost.value) postChoice.value = next ? 'everyone' : 'account'
})
watch(() => props.guestsCanVote, (next) => {
  if (!savingVote.value) voteChoice.value = next ? 'everyone' : 'account'
})

watch(postChoice, async (next, prev) => {
  if (next === prev || savingPost.value) return
  savingPost.value = true
  try {
    const res = await BoardsApi.update(props.slug, { guests_can_post: next === 'everyone' })
    emit('saved', { guestsCanPost: res.data.guests_can_post, guestsCanVote: res.data.guests_can_vote })
    notification.success(res.data.guests_can_post ? 'Гости снова могут предлагать' : 'Предлагать — только с аккаунтом')
  } catch (e) {
    savingPost.value = false
    postChoice.value = prev
    notification.error(errorMessage(e))
    return
  }
  savingPost.value = false
})

watch(voteChoice, async (next, prev) => {
  if (next === prev || savingVote.value) return
  savingVote.value = true
  try {
    const res = await BoardsApi.update(props.slug, { guests_can_vote: next === 'everyone' })
    emit('saved', { guestsCanPost: res.data.guests_can_post, guestsCanVote: res.data.guests_can_vote })
    notification.success(res.data.guests_can_vote ? 'Гости снова могут голосовать' : 'Голосовать — только с аккаунтом')
  } catch (e) {
    savingVote.value = false
    voteChoice.value = prev
    notification.error(errorMessage(e))
    return
  }
  savingVote.value = false
})
</script>
