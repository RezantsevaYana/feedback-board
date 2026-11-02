<template>
  <BoardWorkspace :slug="slug" page="settings">
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

      <!-- not a section of the nav: an action on the whole board, set apart below everything; owner only -->
      <div v-if="isOwner" class="board-settings-delete">
        <p class="note">Доска уйдёт в корзину, из списка досок её можно будет восстановить.</p>
        <button type="button" class="btn btn-danger" @click="deleteBoard(settings.slug, settings.name)">Удалить доску</button>
      </div>
    </template>

    <p v-else class="note">{{ loadError || 'Доску не удалось загрузить.' }} Вернитесь к <RouterLink to="/app/boards">списку досок</RouterLink>.</p>
  </BoardWorkspace>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { type BoardSettings, BoardsApi } from '@/api/boards'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import CategoriesEditor from '@/shared/components/CategoriesEditor.vue'
import StatusesEditor from '@/shared/components/StatusesEditor.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import SettingsGeneral from './components/SettingsGeneral.vue'
import { boardLoadError } from './composables/boardLoadError'
import { useDeleteBoard } from './composables/useDeleteBoard'

// «Настройки» — what the board is: sections with anchors (#general, #categories, #statuses), deletion below them
// (useDeleteBoard). Domains belong to the widget, so they're on «Доступ»; the old /settings#access and #domains go there.
// Name and domains go through BoardsApi; categories and columns reuse the board's editors,
// so the board store opens this project the same way BoardPage does.
const route = useRoute()
const router = useRouter()
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
if (route.hash === '#access' || route.hash === '#domains') {
  void router.replace({ name: 'board-access', params: { slug: slug.value }, hash: route.hash === '#domains' ? '#widget' : '#mode' })
}

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