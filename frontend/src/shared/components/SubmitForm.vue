<template>
  <form class="fb-form" novalidate @submit.prevent="submit">
    <div v-if="askCategory" class="field">
      <span>О чём это?</span>
      <div class="seg">
        <button v-for="c in categories" :key="c.id" type="button" :aria-pressed="form.category === c.id" @click="pickCategory(c.id)">
          <b>{{ c.name }}</b>
        </button>
      </div>
      <span v-if="errors.category" class="err">{{ errors.category }}</span>
    </div>

    <InputComponent
      v-model="form.title"
      label="Заголовок"
      :error="errors.title"
      maxlength="120"
      placeholder="Коротко, в одну строку"
      @input="clearError('title')"
    />

    <TextAreaComponent
      v-model="form.description"
      label="Описание"
      :error="errors.description"
      maxlength="5000"
      placeholder="Что случилось или что хотите и зачем? Чем подробнее, тем быстрее разберёмся"
      @input="clearError('description')"
    />

    <div class="field">
      <span>Скриншоты <small>· до 5 файлов, до 5 МБ</small></span>
      <div
        class="drop"
        :class="{ over: dragOver }"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="(dragOver = false), addFiles($event.dataTransfer?.files ?? [])"
      >
        Перетащите сюда, вставьте Ctrl+V или
        <label>выберите файл<input type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden @change="addFiles(($event.target as HTMLInputElement).files ?? []); ($event.target as HTMLInputElement).value = ''" /></label>
      </div>
      <div v-if="files.length" class="thumbs">
        <div v-for="(f, i) in files" :key="f.preview" class="thumb">
          <img :src="f.preview" alt="" />
          <button type="button" aria-label="Убрать скриншот" @click="removeFile(i)"><AppIcon name="close" :size="12" /></button>
        </div>
      </div>
      <span v-if="errors.files" class="err">{{ errors.files }}</span>
    </div>

    <InputComponent v-model="form.name" label="Ваше имя" hint="по желанию" :error="errors.name" maxlength="80" placeholder="Можно оставить пустым" />

    <p class="note">Предложение и скриншоты будут видны всем на доске.</p>
    <div v-if="serverError" class="alert" role="alert">{{ serverError }}</div>

    <div class="fb-foot">
      <slot name="secondary" />
      <button type="submit" class="btn btn-primary" :disabled="sending">{{ sending ? 'Отправляем…' : 'Отправить' }}</button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import { PostsApi } from '@/api/posts'
import type { Category, Post } from '@/api/types'
import AppIcon from '@/shared/ui/AppIcon.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import TextAreaComponent from '@/shared/ui/TextAreaComponent.vue'

// The one submission form (TZ 5.1), shared by the widget (Shadow DOM, cross-origin) and the board.
const props = defineProps<{
  project: string
  /** The project's categories; with none configured the choice is not shown. */
  categories: Category[]
  /** Service origin when used from the widget on someone else's site; omitted on the board. */
  origin?: string
}>()
const emit = defineEmits<{
  sent: [post: Post]
  cancel: []
}>()

const MAX_FILES = 5
const MAX_BYTES = 5 * 1024 * 1024
const IMAGE = /^image\/(png|jpeg|webp|gif)$/
const NAME_KEY = 'fb-author-name'

const form = reactive({ category: null as number | null, title: '', description: '', name: readName() })
const files = ref<Array<{ file: File; preview: string }>>([])
const errors = reactive<Record<string, string>>({})
const serverError = ref('')
const sending = ref(false)
const dragOver = ref(false)

const askCategory = computed(() => props.categories.length > 0)

function readName(): string {
  try {
    return localStorage.getItem(NAME_KEY) ?? ''
  } catch {
    return ''
  }
}
function saveName() {
  try {
    localStorage.setItem(NAME_KEY, form.name.trim())
  } catch {
    // storage blocked: the name just is not remembered
  }
}

function addFiles(list: FileList | File[]) {
  delete errors.files
  for (const file of Array.from(list)) {
    if (!IMAGE.test(file.type)) {
      errors.files = `«${file.name}» — не картинка. Подойдут PNG, JPG, WebP или GIF.`
      continue
    }
    if (file.size > MAX_BYTES) {
      errors.files = `«${file.name}» больше 5 МБ.`
      continue
    }
    if (files.value.length >= MAX_FILES) {
      errors.files = `Можно приложить не больше ${MAX_FILES} скриншотов.`
      break
    }
    files.value.push({ file, preview: URL.createObjectURL(file) })
  }
}
function clearError(field: string) {
  delete errors[field]
}
function pickCategory(id: number) {
  form.category = id
  clearError('category')
}
function removeFile(i: number) {
  const [removed] = files.value.splice(i, 1)
  if (removed) URL.revokeObjectURL(removed.preview)
  delete errors.files
}

function onPaste(e: ClipboardEvent) {
  const pasted = Array.from(e.clipboardData?.files ?? [])
  if (pasted.length) {
    e.preventDefault()
    addFiles(pasted)
  }
}
onMounted(() => document.addEventListener('paste', onPaste))
onBeforeUnmount(() => {
  document.removeEventListener('paste', onPaste)
  files.value.forEach((f) => URL.revokeObjectURL(f.preview))
})

function validate(): boolean {
  for (const key of Object.keys(errors)) delete errors[key]
  if (askCategory.value && form.category === null) errors.category = 'Выберите, к чему относится предложение.'
  if (form.title.trim().length < 3) errors.title = 'Напишите заголовок, хотя бы 3 символа.'
  if (form.description.trim().length < 10) errors.description = 'Опишите подробнее, хотя бы 10 символов.'
  return Object.keys(errors).length === 0
}

async function submit() {
  serverError.value = ''
  if (!validate() || sending.value) return
  const body = new FormData()
  if (form.category !== null) body.append('category_id', String(form.category))
  body.append('title', form.title.trim())
  body.append('description', form.description.trim())
  if (form.name.trim()) body.append('author_name', form.name.trim())
  files.value.forEach((f) => body.append('files[]', f.file))
  saveName()

  sending.value = true
  try {
    const res = await PostsApi.create(props.project, body, props.origin)
    form.category = null
    form.title = ''
    form.description = ''
    files.value.forEach((f) => URL.revokeObjectURL(f.preview))
    files.value = []
    emit('sent', res.data)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [key, messages] of Object.entries(e.errors)) {
        const field = key.startsWith('files') ? 'files' : key === 'author_name' ? 'name' : key === 'category_id' ? 'category' : key
        if (messages[0]) errors[field] = messages[0]
      }
    } else {
      serverError.value = errorMessage(e)
    }
  } finally {
    sending.value = false
  }
}
</script>
