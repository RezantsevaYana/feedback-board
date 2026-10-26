<template>
  <AppHeader />
  <main class="container base-page board-new">
    <form class="board-new-form" novalidate @submit.prevent="submit">
      <div class="page-head board-new-head">
        <nav class="page-crumbs" aria-label="Путь">
          <RouterLink to="/app/boards">Мои доски</RouterLink>
          <span aria-hidden="true">/</span>
          <span aria-current="page">Новая доска</span>
        </nav>
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

      <fieldset class="board-new-origins">
        <legend>Домены <small>· необязательно</small></legend>
        <p class="note board-new-origins-note">
          Сайты, где у вас будет виджет. Без домена доска работает только по прямой ссылке, виджет можно настроить позже.
        </p>
        <div v-for="(origin, idx) in origins" :key="idx" class="board-new-origin-row">
          <InputComponent
            v-model="origin.value"
            type="text"
            autocapitalize="off"
            spellcheck="false"
            :placeholder="ORIGIN_EXAMPLE"
            :error="originErrors[idx]"
          />
          <button type="button" class="icon-btn" aria-label="Удалить домен" @click="removeOrigin(idx)">
            <AppIcon name="close" />
          </button>
        </div>
        <button type="button" class="btn board-new-add-origin" @click="addOrigin">+ Добавить домен</button>
      </fieldset>

      <div class="board-new-actions">
        <!-- hover rolls the label into «Поехали →» (shared .cta-roll); while sending it reads «Создаём…» -->
        <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending" :aria-label="sending ? undefined : 'Создать доску'">
          <template v-if="sending">Создаём…</template>
          <span v-else class="cta-roll" aria-hidden="true"><span>Создать доску</span><span>Поехали →</span></span>
        </button>
      </div>
    </form>
  </main>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { helpers, maxLength, minLength, required } from '@vuelidate/validators'
import { BoardsApi, type SlugCheck } from '@/api/boards'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { slugify } from '@/shared/utils/slugify'
import AppHeader from '@/shared/components/AppHeader.vue'
import AppIcon from '@/shared/ui/AppIcon.vue'
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

// kept out of the template: a literal "//" there trips vue-tsc and drops the v-for scope
const ORIGIN_EXAMPLE = 'https://app.example.com'
const origins = reactive<Array<{ value: string }>>([{ value: '' }])
const originErrors = ref<Record<number, string>>({})

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
  // The user touched the slug field by hand — don't overwrite it from the name any more.
  slugDirty.value = true
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

function addOrigin() {
  origins.push({ value: '' })
}
function removeOrigin(idx: number) {
  origins.splice(idx, 1)
  if (origins.length === 0) origins.push({ value: '' })
  originErrors.value = {}
}

async function submit() {
  if (sending.value) return
  slugServer.value = ''
  originErrors.value = {}
  const isValid = await v$.value.$validate()
  if (!isValid) return

  const cleanOrigins = origins
    .map((o) => o.value.trim().replace(/\/+$/, ''))
    .filter((v) => v.length > 0)

  sending.value = true
  try {
    const res = await BoardsApi.create({
      name: name.value.trim(),
      slug: slug.value.trim(),
      origins: cleanOrigins,
    })
    auth.addBoard(res.data)
    notification.success(`Доска «${res.data.name}» готова`)
    router.push({ name: 'board-embed', params: { slug: res.data.slug }, query: { created: '1' } })
  } catch (e) {
    if (e instanceof ApiError) {
      slugServer.value = e.field('slug') ?? ''
      if (slugServer.value) slugError.value = slugServer.value
      // origins: errors come in as "origins.0", "origins.1" etc.
      const errs: Record<number, string> = {}
      for (const key of Object.keys(e.errors)) {
        const match = key.match(/^origins\.(\d+)$/)
        if (match && e.errors[key]?.[0]) errs[Number(match[1])] = e.errors[key][0]
      }
      originErrors.value = errs
      if (!slugError.value && !Object.keys(errs).length) notification.error(e.message)
    } else {
      notification.error(String(e))
    }
    v$.value.$touch()
  } finally {
    sending.value = false
  }
}
</script>
