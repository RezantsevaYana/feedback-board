<template>
  <form class="access-mode" novalidate :aria-busy="saving" @submit.prevent="save">
    <!-- picking a card only picks: what changes is listed below, inside the warm panel, with the action button -->
    <RadioCardsComponent v-model="choice" name="visibility" :options="options" />
    <!-- before switching: the list of consequences AND the action buttons live inside one amber panel (prototype
         .mode-confirm). This tells people «this is one careful decision» — not «here are some facts over here,
         and some buttons over there». Primary first, cancel after it. -->
    <div v-if="changed" class="access-mode-effects" aria-live="polite">
      <p class="access-mode-effects-title">{{ choice === 'private' ? 'Что изменится, если закрыть доску' : 'Что изменится, если открыть доску' }}</p>
      <ul>
        <li v-for="line in effects" :key="line">{{ line }}</li>
      </ul>
      <div class="access-mode-actions">
        <button class="btn btn-primary" type="submit" :disabled="saving" :aria-busy="saving">
          {{ saving ? 'Сохраняем…' : choice === 'private' ? 'Сделать закрытой' : 'Открыть для всех' }}
        </button>
        <button type="button" class="btn" :disabled="saving" @click="cancel">Отмена</button>
      </div>
    </div>
    <p v-else class="note">Режим можно сменить в любую сторону — перед этим покажем, что изменится.</p>
  </form>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { type BoardSettings, type BoardVisibility, BoardsApi } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useAuthStore } from '@/store/auth/useAuthStore'
import RadioCardsComponent from '@/shared/ui/RadioCardsComponent.vue'
import { plural } from '@/shared/utils/format'

// «Кто видит доску» at the top of «Доступ»: open or closed, both ways. Picking the other card only picks it: the list
// of what happens to posts, votes, people and the widget appears (docs/ROLES_PLAN.md §5) with «Сделать закрытой» /
// «Открыть для всех» and «Отмена»; the page below is muted until then (`pending`). Closing is one click — it can be
// undone any time. Opening asks once more: what was written «for us» becomes public, and that can't be taken back.
const props = defineProps<{
  settings: BoardSettings
}>()
const emit = defineEmits<{
  saved: [settings: BoardSettings]
  pending: [changing: boolean]
}>()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const options: { value: BoardVisibility; label: string; hint: string }[] = [
  { value: 'public', label: 'Открытая', hint: 'Любой по ссылке читает, голосует и предлагает. Можно поставить виджет на сайт.' },
  { value: 'private', label: 'Закрытая', hint: 'Только участники. Остальные могут запросить доступ. Виджет на сайте скрыт.' },
]
const auth = useAuthStore()

const choice = ref<BoardVisibility>(props.settings.visibility)
const saving = ref(false)

const changed = computed(() => choice.value !== props.settings.visibility)

watch(() => props.settings.visibility, (next) => (choice.value = next))
watch(changed, (v) => emit('pending', v))

function cancel() {
  choice.value = props.settings.visibility
}

const postsText = computed(() => {
  const n = props.settings.posts_count
  return `${n} ${plural(n, 'пост', 'поста', 'постов')}`
})

// one short line per consequence: what people see, the votes, who stays, the widget
const effects = computed(() => (choice.value === 'private'
  ? [
      'Доску увидят только участники, остальные смогут запросить доступ.',
      'Голоса гостей останутся в счётчиках, новых не будет.',
      'Участники пишут и голосуют из аккаунта.',
      'Кнопка виджета исчезнет с сайтов. Вернётся, если снова откроете доску.',
    ]
  : [
      `Любой по ссылке увидит ${postsText.value}, ответы команды и имена авторов. Скрытые останутся скрытыми.`,
      'Голоса участников сохранятся, гости тоже смогут голосовать.',
      'Останется команда — админы и менеджеры. Приглашения участников и читателей отменятся.',
      'Кнопка виджета снова появится на сайтах.',
    ]))

async function save() {
  if (!changed.value || saving.value) return
  const closing = choice.value === 'private'
  // opening shows to everyone what was written for the members only: one more explicit step
  if (!closing) {
    const ok = await confirm.ask({
      title: 'Открыть доску для всех?',
      message: `${postsText.value[0]!.toUpperCase()}${postsText.value.slice(1)} с именами авторов увидит любой, у кого есть ссылка. Люди писали их, рассчитывая, что видят только участники.`,
      confirmText: 'Открыть для всех',
      danger: true,
    })
    if (!ok) return
  }
  saving.value = true
  try {
    const res = await BoardsApi.update(props.settings.slug, { visibility: choice.value })
    emit('saved', res.data)
    auth.setVisibility(res.data.slug, res.data.visibility)
    notification.success(closing ? 'Доска стала закрытой' : 'Доска открыта для всех')
  } catch (e) {
    choice.value = props.settings.visibility
    const field = e instanceof ApiError ? e.field('visibility') : null
    notification.error(field ?? errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>
