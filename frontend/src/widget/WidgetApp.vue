<template>
  <div class="fbw" :class="position === 'bottom-left' ? 'left' : 'right'">
    <div v-if="open" class="fbw-panel" role="dialog" aria-label="Обратная связь">
      <div class="fbw-head">
        <h3>Обратная связь</h3>
        <button type="button" class="icon-btn" aria-label="Закрыть" @click="toggle"><AppIcon name="close" /></button>
      </div>
      <div class="fbw-body">
        <div v-if="sent" class="done-box">
          <span class="done-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
          </span>
          <h3>Спасибо! Предложение на доске</h3>
          <p class="note">Сохраните ссылку: по ней видно статус и ответ команды.</p>
          <div class="post-link">
            <span>{{ postUrl.replace(/^https?:\/\//, '') }}</span>
            <button type="button" class="btn" @click="copy">{{ copied ? 'Скопировано' : 'Скопировать' }}</button>
          </div>
          <div class="row fbw-actions">
            <a class="btn btn-primary" :href="postUrl" target="_blank" rel="noopener">Открыть на доске</a>
            <button type="button" class="btn" @click="again">Отправить ещё</button>
          </div>
        </div>
        <div v-else-if="loadError" class="done-box">
          <p class="alert">{{ loadError }}</p>
          <button type="button" class="btn" @click="loadCategories">Попробовать ещё раз</button>
        </div>
        <p v-else-if="categories === null" class="note">Загружаем…</p>
        <SubmitForm v-else :project="project" :categories="categories" :origin="origin" @sent="sent = $event">
          <template #secondary>
            <a class="link-btn" :href="boardUrl" target="_blank" rel="noopener">Все предложения →</a>
          </template>
        </SubmitForm>
      </div>
    </div>

    <button type="button" class="fbw-launcher slant" :aria-expanded="open" @click="toggle">
      <AppIcon :name="open ? 'close' : 'chat'" />
      {{ open ? 'Закрыть' : 'Обратная связь' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { errorMessage } from '@/api/client'
import { ProjectsApi } from '@/api/projects'
import type { Category, Post } from '@/api/types'
import AppIcon from '@/shared/ui/AppIcon.vue'
import SubmitForm from '@/shared/components/SubmitForm.vue'
import { useEscapeKey } from '@/shared/composables/useEscapeKey'

// Floating button + panel with the shared form. The widget only accepts submissions:
// statuses and voting live on the board (TZ 5.1).
const props = defineProps<{
  project: string
  origin: string
  position?: string
}>()

const open = ref(false)
// categories come from the service on first open (cross-origin GET, allowed for the project's sites)
const categories = ref<Category[] | null>(null)
const loadError = ref('')
const sent = ref<Post | null>(null)
const copied = ref(false)

const boardUrl = computed(() => `${props.origin}/${encodeURIComponent(props.project)}`)
const postUrl = computed(() => (sent.value ? `${boardUrl.value}/posts/${sent.value.id}` : ''))

async function loadCategories() {
  loadError.value = ''
  try {
    const res = await ProjectsApi.get(props.project, props.origin)
    categories.value = res.data.categories
  } catch (e) {
    loadError.value = errorMessage(e)
  }
}

function toggle() {
  open.value = !open.value
  copied.value = false
  if (open.value && categories.value === null) loadCategories()
}
function again() {
  sent.value = null
  copied.value = false
}
async function copy() {
  try {
    await navigator.clipboard.writeText(postUrl.value)
    copied.value = true
  } catch {
    copied.value = false
  }
}
useEscapeKey(() => (open.value = false))
</script>
