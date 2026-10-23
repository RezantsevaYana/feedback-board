<template>
  <AppHeader />
  <main class="container base-page board-settings">
    <div class="board-settings-head">
      <RouterLink to="/app/boards" class="board-settings-back">← Все доски</RouterLink>
      <div class="board-settings-title">
        <h1>{{ settings?.name ?? slug }}</h1>
        <a v-if="settings" :href="`/${slug}`" class="btn" target="_blank" rel="noopener">Открыть доску ↗</a>
      </div>
    </div>

    <p v-if="loading" class="note">Загружаем…</p>

    <div v-else-if="settings" class="board-settings-layout">
      <SettingsNav :slug="slug" />

      <div class="board-settings-sections">
        <section id="general" class="board-settings-section">
          <h2>Общее</h2>
          <SettingsGeneral :settings="settings" @saved="onSaved" />
        </section>

        <section id="categories" class="board-settings-section">
          <h2>Категории</h2>
          <CategoriesEditor v-if="projectReady" />
          <p v-else class="note">{{ board.loadError || 'Загружаем…' }}</p>
        </section>

        <section id="statuses" class="board-settings-section">
          <h2>Столбцы</h2>
          <StatusesEditor v-if="projectReady" />
          <p v-else class="note">{{ board.loadError || 'Загружаем…' }}</p>
        </section>

        <section id="domains" class="board-settings-section">
          <h2>Домены</h2>
          <SettingsDomains :settings="settings" @saved="onSaved" />
        </section>

        <section id="delete" class="board-settings-section danger">
          <h2>Удаление</h2>
          <DeleteBoardSection :settings="settings" />
        </section>
      </div>
    </div>

    <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>
  </main>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { type BoardSettings, BoardsApi } from '@/api/boards'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import CategoriesEditor from '@/shared/components/CategoriesEditor.vue'
import StatusesEditor from '@/shared/components/StatusesEditor.vue'
import DeleteBoardSection from './components/DeleteBoardSection.vue'
import SettingsDomains from './components/SettingsDomains.vue'
import SettingsGeneral from './components/SettingsGeneral.vue'
import SettingsNav from './components/SettingsNav.vue'
import { boardLoadError } from './composables/boardLoadError'

// One page, sections with anchors (#general, #categories, #statuses, #domains, #delete).
// Name and domains go through BoardsApi; categories and columns reuse the board's editors,
// so the board store opens this project the same way BoardPage does.
const route = useRoute()
const auth = useAuthStore()
const board = useBoardStore()

const slug = computed(() => String(route.params.slug ?? ''))
const settings = ref<BoardSettings | null>(null)
const loading = ref(true)
const loadError = ref('')
const projectReady = computed(() => board.project?.slug === slug.value)

watch(slug, load, { immediate: true })

async function load() {
  loading.value = true
  loadError.value = ''
  settings.value = null
  try {
    const res = await BoardsApi.show(slug.value)
    settings.value = res.data
    void board.open(slug.value)
  } catch (e) {
    loadError.value = boardLoadError(e)
  } finally {
    loading.value = false
  }
  // the sections exist only now — scroll to the anchor we arrived with (e.g. /settings#domains)
  if (settings.value && route.hash) {
    await nextTick()
    document.querySelector(route.hash)?.scrollIntoView()
  }
}

function onSaved(next: BoardSettings) {
  if (settings.value && next.name !== settings.value.name) {
    auth.renameBoard(next.slug, next.name)
    if (board.project?.slug === next.slug) board.project.name = next.name
  }
  settings.value = next
}
</script>