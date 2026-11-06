<template>
  <div class="onboarding">
    <!-- the strip, the step and the bar sit in the page's .container, as every other page -->
    <header class="onboarding-top">
      <div class="container onboarding-top-inner">
      <RouterLink to="/app/boards" class="app-brand">Feedback Board</RouterLink>
      <!-- phone: the short brand tag, as in AppHeader -->
      <RouterLink to="/app/boards" class="app-brand-mark slant" aria-label="Feedback Board">FB</RouterLink>
      <span class="onboarding-board">{{ boardName ? `Новая доска «${boardName}»` : 'Новая доска' }}</span>
      <div class="onboarding-progress">
        <div class="onboarding-bars" role="progressbar" aria-label="Настройка доски" aria-valuemin="1" :aria-valuemax="STEPS" :aria-valuenow="step">
          <i v-for="n in STEPS" :key="n" :class="{ done: n < step, cur: n === step && !done }" />
        </div>
        <span class="onboarding-progress-label">{{ done ? 'Готово' : `Шаг ${step} из ${STEPS}` }}</span>
      </div>
      <!-- step 1: nothing is created yet, so it's «Отменить создание» (back to «Мои доски»);
           later — «Настроить потом»: the rest keeps its defaults, the same as «Пропустить настройку» on step 2 -->
      <button v-if="!done" type="button" class="link-btn onboarding-later" :disabled="busy" @click="later">{{ step === 1 ? 'Отменить создание' : 'Настроить потом' }}</button>
      </div>
    </header>

    <main class="container onboarding-main">
      <div class="onboarding-current">
      <StepName v-if="step === 1" ref="current" @created="onCreated" @busy="busy = $event" @draft="draft = $event" />
      <StepVisibility v-else-if="step === 2" ref="current" :slug="slug" :visibility="visibility" @saved="visibility = $event" @pick="picked = $event" @skip-all="finish" />
      <StepWhere v-else-if="step === 5" ref="current" :slug="slug" :visibility="visibility" />
      <!-- categories and statuses work through the board store, as the editors in the settings do -->
      <template v-else-if="boardReady">
        <StepCategories v-if="step === 3" ref="current" />
        <StepStatuses v-else-if="step === 4" ref="current" />
        <StepIdea v-else-if="step === 6" ref="current" :slug="slug" @draft="idea = $event" @published="published = $event" />
        <StepDone v-else-if="done" :slug="slug" :published="published" />
      </template>
      <p v-else class="note">Загружаем…</p>
      </div>
      <!-- what the board will look like, the part this step changes framed in red -->
      <OnboardingPreview v-if="!done" :step="step" :name="boardName || draft.name" :slug="slug || draft.slug" :visibility="step === 2 ? picked ?? visibility : visibility" :idea="idea" />
    </main>

    <footer v-if="!done" class="onboarding-bar">
      <!-- the bar is as wide as the step + the preview above it, so «Дальше» sits under the preview's edge -->
      <div class="container"><div class="onboarding-bar-inner">
      <button v-if="step > 1" type="button" class="btn onboarding-back" aria-label="Назад" :disabled="busy" @click="go(prevStep(step))">
        <span class="onboarding-full">← Назад</span><span class="onboarding-short" aria-hidden="true">←</span>
      </button>
      <span class="spacer" />
      <!-- from step 3 on every step has a fine default: skipping keeps it -->
      <button v-if="step >= 3" type="button" class="btn onboarding-skip" :disabled="busy" @click="advance">{{ step === 6 ? 'Без первой идеи' : 'Пропустить' }}</button>
      <button
        type="button"
        class="btn btn-primary onboarding-next"
        :disabled="busy"
        :aria-busy="busy"
        @click="next"
      >
        {{ busy ? 'Сохраняем…' : step === 1 ? 'Создать доску' : step === 6 ? 'Опубликовать' : 'Дальше' }}
      </button>
      </div></div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { type Board, type BoardVisibility, BoardsApi } from '@/api/boards'
import { errorMessage } from '@/api/client'
import type { PaletteColor } from '@/api/types'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import OnboardingPreview from './components/OnboardingPreview.vue'
import StepCategories from './components/StepCategories.vue'
import StepDone from './components/StepDone.vue'
import StepIdea from './components/StepIdea.vue'
import StepName from './components/StepName.vue'
import StepStatuses from './components/StepStatuses.vue'
import StepVisibility from './components/StepVisibility.vue'
import StepWhere from './components/StepWhere.vue'

// The new-board onboarding (TEST.md п. 20, prototype frontend/demo/onboarding-prototype.html): a focused screen
// without the cabinet's side menu, six steps with a progress bar. «Новая доска» opens step 1 here; the board is
// created on it, and the step the owner is on is saved to the board (onboarding_step), so a reload lands on the same
// step. Anything skipped keeps its defaults (open board, no domains, «Баг» / «Идея», the base statuses) and is set up
// later in the settings — there's no «continue the setup» on the hub. A step component does its own saving and
// exposes submit().
// After the last step — the «Доска готова» screen (step 7, not counted in the progress).
const STEPS = 6
const IMPLEMENTED = new Set<number>([1, 2, 3, 4, 5, 6])

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const board = useBoardStore()
const notification = useNotificationStore()

const slug = ref(route.params.slug ? String(route.params.slug) : '')
const boardName = ref('')
const visibility = ref<BoardVisibility>('public')
// step 2: the mode clicked but not saved yet — the preview shows it right away
const picked = ref<BoardVisibility | null>(null)
// step 1, before the board exists: what is typed, for the preview
const draft = ref({ name: '', slug: '' })
// step 6: the first post as it's typed (the preview's card), and its title once published (the done screen)
const idea = ref<{ title: string; category: { name: string; color: PaletteColor | null } | null }>({ title: '', category: null })
const published = ref('')
const done = computed(() => step.value > STEPS)
const boardReady = computed(() => !!slug.value && board.project?.slug === slug.value)
const step = ref(1)
const busy = ref(false)
// every step exposes submit(): true — saved, go on; false — stay (an error is already shown)
const current = ref<{ submit: () => Promise<boolean | void> } | null>(null)

const nextStep = (from: number) => [...IMPLEMENTED].filter((n) => n > from).sort((a, b) => a - b)[0] ?? null
const prevStep = (from: number) => [...IMPLEMENTED].filter((n) => n > 1 && n < from).sort((a, b) => b - a)[0] ?? from

// /app/boards/:slug/setup — «Продолжить» from the hub: back to the saved step
watch(() => route.params.slug, async (value) => {
  if (!value) return
  slug.value = String(value)
  try {
    const res = await BoardsApi.show(slug.value)
    boardName.value = res.data.name
    visibility.value = res.data.visibility
    void board.open(slug.value)
    const saved = res.data.onboarding_step
    if (saved && IMPLEMENTED.has(saved)) step.value = saved
    // nothing to continue (finished or hidden): the board's settings
    else void router.replace({ name: 'board-settings', params: { slug: slug.value } })
  } catch (e) {
    notification.error(errorMessage(e))
    void router.replace('/app/boards')
  }
}, { immediate: true })

const atSetup = computed(() => route.name === 'board-setup')

async function next() {
  if (!current.value || busy.value) return
  // step 1 moves on by itself, through «created»
  if (step.value === 1) return void current.value.submit()
  busy.value = true
  const ok = await current.value.submit()
  busy.value = false
  if (ok) await advance()
}

async function advance() {
  const following = nextStep(step.value)
  if (following) await go(following)
  else await finish()
}

async function onCreated(created: Board) {
  auth.addBoard(created)
  slug.value = created.slug
  boardName.value = created.name
  visibility.value = created.visibility
  void board.open(created.slug)
  await advance()
}

async function go(target: number) {
  step.value = target
  picked.value = null
  // saved first: if the page is rebuilt on the address change below, it reads this step back from the board
  await BoardsApi.update(slug.value, { onboarding_step: target }).catch(() => undefined)
  // the address bar follows the board, so a reload lands on the same step
  if (!atSetup.value) await router.replace({ name: 'board-setup', params: { slug: slug.value } })
}

// the last step done: nothing left to continue from the hub, the «Доска готова» screen
async function finish() {
  await BoardsApi.update(slug.value, { onboarding_step: null }).catch(() => undefined)
  step.value = STEPS + 1
}

// step 1: nothing is created yet — just leave; later: the rest stays as it is, the «Доска готова» screen
function later() {
  if (step.value === 1) void router.push('/app/boards')
  else void finish()
}
</script>
