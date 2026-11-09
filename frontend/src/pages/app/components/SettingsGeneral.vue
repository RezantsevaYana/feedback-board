<template>
  <form class="settings-general" novalidate @submit.prevent="save">
    <InputComponent
      v-model="name"
      label="Название доски"
      type="text"
      maxlength="80"
      :error="nameError"
      @input="nameError = ''"
    />
    <div class="settings-general-slug">
      <InputComponent
        v-model="slug"
        label="Адрес доски"
        :prefix="addressPrefix"
        type="text"
        autocapitalize="off"
        spellcheck="false"
        maxlength="64"
        :error="slugError"
      />
      <p v-if="slugHint" class="settings-general-slug-hint" :class="slugHintClass">{{ slugHint }}</p>
      <p v-if="slugChanged" class="note settings-general-slug-warn">
        Старый адрес продолжит работать — ссылки и виджет не сломаются.
      </p>
    </div>
    <div class="settings-actions">
      <button class="btn btn-primary" type="submit" :disabled="!canSave" :aria-busy="saving">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { type BoardSettings, BoardsApi, type SlugCheck } from '@/api/boards'
import { ApiError, errorMessage } from '@/api/client'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Board name and address (test.md п. 24). The slug is editable: on save the old value goes to
// project_slug_history, so already-shared links and widgets keep working — the resolver quietly
// swaps them for the new slug. Live availability check mirrors StepName of the onboarding.
const props = defineProps<{
  settings: BoardSettings
}>()
const emit = defineEmits<{
  saved: [settings: BoardSettings]
}>()
const notification = useNotificationStore()
const addressPrefix = `${window.location.host}/`

const name = ref(props.settings.name)
const slug = ref(props.settings.slug)
const nameError = ref('')
const slugError = ref('')
const slugServer = ref('')
const slugCheck = ref<SlugCheck | null>(null)
const slugChecking = ref(false)
const saving = ref(false)

// settings.name / slug can change under us after a save — keep the fields in sync when the parent passes a fresh copy
watch(() => props.settings.name, (next) => (name.value = next))
watch(() => props.settings.slug, (next) => {
  slug.value = next
  slugCheck.value = null
  slugServer.value = ''
  slugError.value = ''
})

const slugChanged = computed(() => slug.value.trim() !== props.settings.slug)
const nameChanged = computed(() => name.value.trim() !== props.settings.name)
const changed = computed(() => nameChanged.value || slugChanged.value)

// before submit: block save until the address looks good — format is fine, server says it's free (or unchanged)
const slugOk = computed(() => {
  if (!slugChanged.value) return true
  if (!SLUG_FORMAT.test(slug.value.trim())) return false
  if (slugChecking.value) return false
  return slugCheck.value?.available ?? false
})
const canSave = computed(() => changed.value && !saving.value && slugOk.value)

const SLUG_FORMAT = /^[a-z0-9][a-z0-9-]{1,63}$/

// live availability check — debounced, only the last typed value matters (StepName uses the same shape)
let slugTimer: ReturnType<typeof setTimeout> | null = null
watch(slug, (next) => {
  slugServer.value = ''
  slugError.value = ''
  slugCheck.value = null
  if (slugTimer) clearTimeout(slugTimer)
  const value = next.trim()
  if (!slugChanged.value || !SLUG_FORMAT.test(value)) return
  slugChecking.value = true
  slugTimer = setTimeout(() => void runSlugCheck(value), 400)
})
async function runSlugCheck(value: string) {
  try {
    const res = await BoardsApi.checkSlug(value, props.settings.slug)
    if (slug.value.trim() === value) slugCheck.value = res.data
  } catch {
    // network / 500: the server validates on submit anyway
  } finally {
    if (slug.value.trim() === value) slugChecking.value = false
  }
}

const slugHint = computed(() => {
  if (!slugChanged.value) return ''
  const value = slug.value.trim()
  if (value.length < 2) return 'Адрес — хотя бы 2 символа.'
  if (!SLUG_FORMAT.test(value)) return 'Только латиница, цифры и дефис. Начинается с буквы или цифры.'
  if (slugChecking.value) return 'Проверяем…'
  const info = slugCheck.value
  if (!info) return ''
  if (info.available) return '✓ Свободен'
  switch (info.reason) {
    case 'reserved': return 'Этот адрес зарезервирован. Выберите другой.'
    case 'taken': return 'Адрес занят. Попробуйте другой.'
    default: return 'Этот адрес нельзя использовать.'
  }
})
const slugHintClass = computed(() => {
  if (!slugChanged.value || slugChecking.value) return ''
  const value = slug.value.trim()
  if (value.length < 2 || !SLUG_FORMAT.test(value)) return 'err'
  if (!slugCheck.value) return ''
  return slugCheck.value.available ? 'ok' : 'err'
})

async function save() {
  if (!canSave.value) return
  const trimmed = { name: name.value.trim(), slug: slug.value.trim() }
  if (!trimmed.name) {
    nameError.value = 'Назовите доску — чтобы на неё было легко сослаться.'
    return
  }
  saving.value = true
  try {
    const body: { name?: string; slug?: string } = {}
    if (trimmed.name !== props.settings.name) body.name = trimmed.name
    if (trimmed.slug !== props.settings.slug) body.slug = trimmed.slug
    const res = await BoardsApi.update(props.settings.slug, body)
    emit('saved', res.data)
    notification.success(body.slug ? 'Адрес сохранён. Старые ссылки продолжат работать.' : 'Название сохранено')
  } catch (e) {
    if (e instanceof ApiError) {
      const nameField = e.field('name')
      const slugField = e.field('slug')
      if (nameField) nameError.value = nameField
      if (slugField) {
        slugServer.value = slugField
        slugError.value = slugField
      }
      if (!nameField && !slugField) notification.error(errorMessage(e))
    } else {
      notification.error(errorMessage(e))
    }
  } finally {
    saving.value = false
  }
}
</script>
