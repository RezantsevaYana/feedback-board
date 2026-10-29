<template>
  <AppHeader />
  <main class="container base-page board-workspace-page">
    <BoardWorkspace :slug="slug" :name="boardName" page="settings">
      <p v-if="loading" class="note">Загружаем…</p>

      <template v-else-if="settings">
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

        <section id="access" class="board-settings-section">
          <h2>Доступ</h2>
          <SettingsAccess :settings="settings" @saved="onSaved" />
        </section>

        <section id="domains" class="board-settings-section">
          <h2>Домены</h2>
          <SettingsDomains :settings="settings" @saved="onSaved" />
        </section>

        <!-- not a section of the nav: an action on the whole board, set apart below everything; owner only -->
        <div v-if="isOwner" class="board-settings-delete">
          <p class="note">Доска уйдёт в корзину, из списка досок её можно будет восстановить.</p>
          <button type="button" class="btn btn-danger" @click="deleteBoard(settings.slug, settings.name)">Удалить доску</button>
        </div>
      </template>

      <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>
    </BoardWorkspace>
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
import BoardWorkspace from './components/BoardWorkspace.vue'
import SettingsAccess from './components/SettingsAccess.vue'
import SettingsDomains from './components/SettingsDomains.vue'
import SettingsGeneral from './components/SettingsGeneral.vue'
import { boardLoadError } from './composables/boardLoadError'
import { useDeleteBoard } from './composables/useDeleteBoard'

// One page, sections with anchors (#general, #categories, #statuses, #access, #domains); deletion sits below them (useDeleteBoard).
// Name and domains go through BoardsApi; categories and columns reuse the board's editors,
// so the board store opens this project the same way BoardPage does.
const route = useRoute()
const auth = useAuthStore()
const board = useBoardStore()
const { deleteBoard } = useDeleteBoard()

const slug = computed(() => String(route.params.slug ?? ''))
const settings = ref<BoardSettings | null>(null)
const loading = ref(true)
const loadError = ref('')
const projectReady = computed(() => board.project?.slug === slug.value)
// admins open settings too, but deleting the board stays with its owner (the API checks owner_id)
const isOwner = computed(() => auth.me?.projects.find((b) => b.slug === slug.value)?.role === 'owner')
// the head shows the name from /me right away, before the settings arrive
const boardName = computed(() => settings.value?.name ?? auth.boardSummaries.find((b) => b.slug === slug.value)?.name ?? slug.value)

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