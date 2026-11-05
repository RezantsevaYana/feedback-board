<template>
  <!-- the owner's colour arrives with the project as custom properties (the only inline style: it's data, not design) -->
  <div ref="root" class="fbw" :class="[position === 'bottom-left' ? 'left' : 'right', { ready }]" :style="palette">
    <Transition name="fbw-panel">
      <div v-if="open" class="fbw-panel" role="dialog" aria-label="Обратная связь">
        <div class="fbw-head">
          <div class="fbw-title">
            <h3>Обратная связь</h3>
            <a class="fbw-board" :href="boardUrl" target="_blank" rel="noopener">{{ projectName ? `Доска ${projectName}` : 'Все предложения' }} ↗</a>
          </div>
          <button type="button" class="icon-btn" aria-label="Закрыть" @click="toggle"><AppIcon name="close" /></button>
        </div>
        <div class="fbw-body">
          <div v-if="sent" class="done-box">
            <span class="done-icon">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
            </span>
            <h3>Спасибо! Предложение на доске</h3>
            <p class="note">Сохраните ссылку: по ней видно статус и ответ команды.</p>
            <div class="post-link">
              <span>{{ postUrl.replace(/^https?:\/\//, '') }}</span>
              <button type="button" class="btn btn-sm" @click="copy">{{ copied ? 'Скопировано' : 'Скопировать' }}</button>
            </div>
            <div class="fbw-actions">
              <a class="btn btn-primary" :href="postUrl" target="_blank" rel="noopener">Открыть на доске</a>
              <button type="button" class="btn" @click="again">Отправить ещё</button>
            </div>
          </div>
          <!-- the board was closed while the widget stayed on the site: say so, and lead members to the board.
               The same words are previewed on the cabinet's «Доступ» page (BoardAccessPage) -->
          <div v-else-if="locked" class="done-box">
            <h3>Доска закрыта</h3>
            <p class="note">Предложения здесь больше не принимаются. Участники могут открыть доску по ссылке.</p>
            <a class="btn btn-primary" :href="boardUrl" target="_blank" rel="noopener">Открыть доску ↗</a>
          </div>
          <div v-else-if="loadError" class="done-box">
            <p class="alert">{{ loadError }}</p>
            <button type="button" class="btn" @click="loadCategories">Попробовать ещё раз</button>
          </div>
          <!-- the project's categories are on their way: the form's own rows in placeholders -->
          <div v-else-if="categories === null" class="fbw-skeleton" role="status">
            <span class="visually-hidden">Загружаем…</span>
            <span class="sk sk-meta" aria-hidden="true" />
            <span class="sk sk-input" aria-hidden="true" />
            <span class="sk sk-meta" aria-hidden="true" />
            <span class="sk sk-input" aria-hidden="true" />
            <span class="sk sk-input fbw-sk-area" aria-hidden="true" />
            <span class="sk sk-input" aria-hidden="true" />
          </div>
          <SubmitForm v-else :project="project" :categories="categories" :origin="origin" @sent="sent = $event" />
        </div>
      </div>
    </Transition>


    <!-- closed: the brand tag «Обратная связь»; open: a round × (the panel has its own close too) -->
    <button
      type="button"
      class="fbw-launcher"
      :class="open ? 'open' : 'slant'"
      :aria-expanded="open"
      :aria-label="open ? 'Закрыть обратную связь' : undefined"
      @click="toggle"
    >
      <AppIcon :name="open ? 'close' : 'chat'" :size="open ? 16 : 14" />
      <span v-if="!open" class="fbw-launcher-label">{{ launcherText || 'Обратная связь' }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ApiError, errorMessage } from '@/api/client'
import { ProjectsApi } from '@/api/projects'
import type { Category, Post } from '@/api/types'
import AppIcon from '@/shared/ui/AppIcon.vue'
import SubmitForm from '@/shared/components/SubmitForm.vue'
import { useEscapeKey } from '@/shared/composables/useEscapeKey'
import { widgetPalette } from '@/shared/utils/widgetColor'

// Floating button + panel with the shared form. The widget only accepts submissions:
// statuses and voting live on the board (TZ 5.1).
const props = defineProps<{
  project: string
  origin: string
  position?: string
}>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
// the project (categories, name, colour) comes from the service as soon as the widget mounts:
// one cross-origin GET, allowed for the project's sites; opening the panel then needs no wait
const categories = ref<Category[] | null>(null)
const projectName = ref('')
const color = ref<string | null>(null)
// custom launcher label from the project; empty string → the default stays
const launcherText = ref('')
// the launcher waits for the project (or its failure), so the service's green never flashes before the owner's colour
const ready = ref(false)
const palette = computed(() => widgetPalette(color.value))
const loadError = ref('')
// private board + anonymous visitor → EnsureProjectPublished answers 403 with reason=login_required
const locked = ref(false)
const sent = ref<Post | null>(null)
const copied = ref(false)

const boardUrl = computed(() => `${props.origin}/${encodeURIComponent(props.project)}`)
const postUrl = computed(() => (sent.value ? `${boardUrl.value}/posts/${sent.value.id}` : ''))

async function loadCategories() {
  loadError.value = ''
  locked.value = false
  try {
    const res = await ProjectsApi.get(props.project, props.origin)
    categories.value = res.data.categories
    projectName.value = res.data.name
    color.value = res.data.widget_color
    launcherText.value = res.data.widget_launcher_text ?? ''
  } catch (e) {
    if (e instanceof ApiError && e.status === 403 && e.reason === 'login_required') {
      locked.value = true
    } else {
      loadError.value = errorMessage(e)
    }
  } finally {
    ready.value = true
  }
}

function toggle() {
  open.value = !open.value
  copied.value = false
  // a failed first load is retried from the panel («Попробовать ещё раз»)
  if (open.value && categories.value === null && !loadError.value) loadCategories()
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

// Клик вне виджета закрывает панель. Виджет живёт в Shadow DOM, поэтому
// проверяем composedPath — он пробивает теневую границу и видит .fbw.
function onDocPointer(e: Event) {
  if (!open.value || !root.value) return
  if (!e.composedPath().includes(root.value)) open.value = false
}
onMounted(() => {
  document.addEventListener('pointerdown', onDocPointer, true)
  loadCategories()
})
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocPointer, true))
</script>
