<template>
  <form class="onboarding-step" novalidate @submit.prevent="submit">
    <h1>Как назовём доску?</h1>
    <p class="onboarding-lead">Обычно — по названию продукта. Его увидят все, кто откроет доску.</p>

    <InputComponent
      v-model="name"
      label="Название"
      class="onboarding-name"
      type="text"
      maxlength="80"
      :errors="v$.name"
      validate-on-submit
      autofocus
    />

    <div class="onboarding-slug">
      <InputComponent
        v-model="slug"
        label="Адрес доски"
        :prefix="`${host}/`"
        type="text"
        autocapitalize="off"
        spellcheck="false"
        maxlength="64"
        :error="slugError"
        :errors="slugError ? undefined : v$.slug"
        validate-on-submit
        @input="onSlugInput"
      />
      <p class="onboarding-slug-hint" :class="slugHintClass">{{ slugHint }}</p>
    </div>
    <!-- Enter in a field submits; the visible button is in the page's bottom bar -->
    <button type="submit" hidden />
  </form>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { helpers, maxLength, minLength, required } from '@vuelidate/validators'
import { type Board, BoardsApi, type SlugCheck } from '@/api/boards'
import { ApiError } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { slugify } from '@/shared/utils/slugify'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Onboarding step 1 (TEST.md п. 20): the board's name and address. The board is created here, so every later step
// works on a real board and leaving half way loses nothing. The address follows the name until edited by hand,
// with a live availability check (the logic of the former BoardNewPage).
const emit = defineEmits<{
  created: [board: Board]
  busy: [busy: boolean]
  draft: [draft: { name: string; slug: string }]
}>()
const notification = useNotificationStore()
// the board lives in this same app, at /<address>
const host = window.location.host

const name = ref('')
const slug = ref('')
// the user took over the address: stop following the name
const slugDirty = ref(false)
const slugServer = ref('')
const slugCheck = ref<SlugCheck | null>(null)
const slugChecking = ref(false)
const sending = ref(false)

const slugFormat = helpers.regex(/^[a-z0-9][a-z0-9-]{1,63}$/)
const rules = {
  name: {
    required: helpers.withMessage('Назовите доску — чтобы на неё было легко сослаться.', required),
    max: helpers.withMessage('Название — не длиннее 80 символов.', maxLength(80)),
  },
  slug: {
    required: helpers.withMessage('Введите адрес доски.', required),
    min: helpers.withMessage('Адрес — хотя бы 2 символа.', minLength(2)),
    format: helpers.withMessage('Только латиница, цифры и дефис. Начинается с буквы или цифры.', slugFormat),
    server: helpers.withMessage(() => slugServer.value, () => !slugServer.value),
  },
}
const v$ = useVuelidate(rules, { name, slug })
// the preview next to the form shows the name and the address as they are typed
watch([name, slug], () => emit('draft', { name: name.value.trim(), slug: slug.value.trim() }))

watch(name, (next) => {
  if (!slugDirty.value) slug.value = slugify(next)
})
watch(slug, (next) => {
  slugServer.value = ''
  scheduleSlugCheck(next)
})

function onSlugInput() {
  // clearing the address goes back to following the name
  slugDirty.value = slug.value.length > 0
}

let slugTimer: ReturnType<typeof setTimeout> | null = null
function scheduleSlugCheck(value: string) {
  if (slugTimer) clearTimeout(slugTimer)
  slugCheck.value = null
  if (value.length < 2) return
  slugChecking.value = true
  slugTimer = setTimeout(() => void runSlugCheck(value), 400)
}
async function runSlugCheck(value: string) {
  try {
    const res = await BoardsApi.checkSlug(value)
    // only the last typed value matters
    if (slug.value === value) slugCheck.value = res.data
  } catch {
    // network / 500: the server validates on submit anyway
  } finally {
    if (slug.value === value) slugChecking.value = false
  }
}

const slugError = ref('')
watch([slug, () => v$.value.slug.$errors.length], () => (slugError.value = ''))

const slugHint = ref('')
const slugHintClass = ref('')
watch([slugChecking, slugCheck, slug], () => {
  if (!slug.value) {
    slugHint.value = 'Соберётся из названия — можно поправить. Латиница, цифры, дефис.'
    slugHintClass.value = ''
    return
  }
  if (slugChecking.value) {
    slugHint.value = 'Проверяем…'
    slugHintClass.value = ''
    return
  }
  const info = slugCheck.value
  if (!info) {
    slugHint.value = ''
    slugHintClass.value = ''
    return
  }
  slugHint.value = info.available ? `✓ Свободен. Поменять потом можно в настройках.` : reasonMessage(info.reason)
  slugHintClass.value = info.available ? 'ok' : 'err'
}, { immediate: true })

function reasonMessage(reason: SlugCheck['reason']): string {
  switch (reason) {
    case 'format': return 'Только латиница, цифры и дефис.'
    case 'reserved': return 'Этот адрес зарезервирован. Выберите другой.'
    case 'taken': return 'Адрес занят. Попробуйте добавить цифру.'
    default: return ''
  }
}

async function submit() {
  if (sending.value) return
  slugServer.value = ''
  if (!(await v$.value.$validate())) return

  sending.value = true
  emit('busy', true)
  try {
    const res = await BoardsApi.create({ name: name.value.trim(), slug: slug.value.trim() })
    emit('created', res.data)
  } catch (e) {
    if (e instanceof ApiError) {
      slugServer.value = e.field('slug') ?? ''
      if (slugServer.value) slugError.value = slugServer.value
      else notification.error(e.message)
    } else {
      notification.error(String(e))
    }
    v$.value.$touch()
  } finally {
    sending.value = false
    emit('busy', false)
  }
}

defineExpose({ submit })
</script>
