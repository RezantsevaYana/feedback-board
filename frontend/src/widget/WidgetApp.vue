<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import type { Category, Item, Post, Project } from '@/api/types'
import SubmitForm from '@/components/SubmitForm.vue'

// Floating button + panel with the shared form. The widget only accepts submissions:
// statuses and voting live on the board (TZ 5.1).
const props = defineProps<{ project: string; origin: string; position?: string }>()

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
    const res = await api<Item<Project>>(`/public/projects/${encodeURIComponent(props.project)}`, { origin: props.origin })
    categories.value = res.data.categories
  } catch (e) {
    loadError.value = e instanceof ApiError ? e.message : String(e)
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
function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape' && open.value) open.value = false
}
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="fbw" :class="position === 'bottom-left' ? 'left' : 'right'">
    <div v-if="open" class="panel" role="dialog" aria-label="Обратная связь">
      <div class="head">
        <h3>Обратная связь</h3>
        <button type="button" class="icon-btn" aria-label="Закрыть" @click="toggle">✕</button>
      </div>
      <div class="body">
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
          <div class="row" style="justify-content: center">
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

    <button type="button" class="launcher slant" :aria-expanded="open" @click="toggle">
      <svg v-if="!open" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 3h12v8H6l-4 3V3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" /></svg>
      <span v-else aria-hidden="true">✕</span>
      {{ open ? 'Закрыть' : 'Обратная связь' }}
    </button>
  </div>
</template>

<style src="../assets/tokens.css"></style>
<style>
/* the host element itself still matches the site's selectors (e.g. `* { font-family: … }`),
   so typography is set on our own root instead of being inherited from :host */
:host { all: initial; }
.fbw {
  font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  font-size: 15px;
  line-height: 1.5;
  color: var(--fg);
}
* { box-sizing: border-box; }
h3 { margin: 0; }
a.btn { text-decoration: none; }
.fbw { position: fixed; bottom: 20px; z-index: 2147483000; display: grid; gap: 12px; justify-items: end; }
.fbw.right { right: 20px; }
.fbw.left { left: 20px; justify-items: start; }
.launcher {
  border: 0;
  padding: 11px 18px 11px 22px;
  background: var(--accent);
  color: var(--accent-fg);
  font: inherit;
  font-weight: 600;
  display: flex;
  gap: 8px;
  align-items: center;
  cursor: pointer;
}
.panel {
  width: 370px;
  max-height: min(640px, calc(100vh - 100px));
  display: grid;
  grid-template-rows: auto 1fr;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 16px;
  box-shadow: var(--shadow);
  overflow: hidden;
}
.head { display: flex; align-items: center; justify-content: space-between; padding: 12px 12px 10px 16px; border-bottom: 1px solid var(--line); }
.head h3 { font-size: 15px; font-weight: 600; }
.body { overflow-y: auto; padding: 14px 16px 16px; }
@media (max-width: 480px) {
  .panel { position: fixed; inset: 0; width: auto; max-height: none; border-radius: 0; border: 0; }
}
@media (prefers-reduced-motion: no-preference) {
  .panel { animation: rise 0.18s ease-out; }
  @keyframes rise { from { transform: translateY(8px); opacity: 0.4; } }
}
</style>