<template>
  <form class="board-new-form" novalidate @submit.prevent="submit">
    <div class="page-head board-new-head">
      <h1>Новая доска</h1>
    </div>

    <InputComponent
      v-model="name"
      label="Название доски"
      hint="пользователи увидят его в шапке"
      class="board-new-name"
      type="text"
      maxlength="80"
      :errors="v$.name"
      validate-on-submit
      autofocus
    />

    <div class="board-new-slug">
      <InputComponent
        v-model="slug"
        label="Адрес доски"
        hint="латиница, цифры, дефис"
        type="text"
        autocapitalize="off"
        spellcheck="false"
        maxlength="64"
        :error="slugError"
        :errors="slugError ? undefined : v$.slug"
        validate-on-submit
        @input="onSlugInput"
      />
      <p class="board-new-slug-hint" :class="slugHintClass">{{ slugHint }}</p>
    </div>

    <div class="board-new-actions">
      <!-- hover rolls the label into «Поехали →» (shared .cta-roll); while sending it reads «Создаём…» -->
      <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending" :aria-label="sending ? undefined : 'Создать доску'">
        <template v-if="sending">Создаём…</template>
        <span v-else class="cta-roll" aria-hidden="true"><span>Создать доску</span><span>Поехали →</span></span>
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { helpers, maxLength, minLength, required } from '@vuelidate/validators'
import { BoardsApi, type SlugCheck } from '@/api/boards'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { slugify } from '@/shared/utils/slugify'
import InputComponent from '@/shared/ui/InputComponent.vue'

const auth = useAuthStore()
const notification = useNotificationStore()
const router = useRouter()

const name = ref('')
const slug = ref('')
// User took over the slug input: stop auto-syncing from name.
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

watch(name, (next) => {
  if (!slugDirty.value) slug.value = slugify(next)
})
watch(slug, (next) => {
  slugServer.value = ''
  scheduleSlugCheck(next)
})

function onSlugInput() {
  // Clearing the slug input resumes auto-generation from the name field.
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
    // discard the response if the user kept typing — only the last value matters
    if (slug.value === value) slugCheck.value = res.data
  } catch {
    // network / 500 — ignore; the server validates on submit anyway
  } finally {
    if (slug.value === value) slugChecking.value = false
  }
}

const slugError = ref('')
watch([slug, () => v$.value.slug.$errors.length], () => (slugError.value = ''))

const slugHint = ref('')
const slugHintClass = ref('')
watch([slugChecking, slugCheck, slug, () => v$.value.slug.$dirty], () => {
  if (!slug.value) {
    slugHint.value = ''
    slugHintClass.value = ''
    return
  }
  if (slugChecking.value) {
    slugHint.value = 'Проверяем…'
    slugHintClass.value = 'muted'
    return
  }
  const info = slugCheck.value
  if (!info) {
    slugHint.value = ''
    slugHintClass.value = ''
    return
  }
  if (info.available) {
    slugHint.value = '✓ Свободно'
    slugHintClass.value = 'ok'
    return
  }
  slugHint.value = reasonMessage(info.reason)
  slugHintClass.value = 'err'
})

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
  const isValid = await v$.value.$validate()
  if (!isValid) return

  sending.value = true
  try {
    const res = await BoardsApi.create({
      name: name.value.trim(),
      slug: slug.value.trim(),
    })
    auth.addBoard(res.data)
    notification.success(`Доска «${res.data.name}» готова`)
    router.push({ name: 'board-access', params: { slug: res.data.slug }, query: { created: '1' } })
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
  }
}
</script>
