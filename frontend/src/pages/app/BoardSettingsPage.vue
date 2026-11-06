<template>
  <BoardWorkspace :slug="slug" page="settings">
    <p v-if="loading" class="note">Загружаем…</p>

    <template v-else-if="settings">
      <!-- no «Общее» heading here: the page is called that already -->
      <section id="general" class="board-settings-section">
        <SettingsGeneral :settings="settings" @saved="onSaved" />
      </section>

      <!-- the link is what the board is for: here, not inside «Доступ» (TEST.md п. 42) -->
      <section id="link" class="board-settings-section">
        <h2>Ссылка на доску</h2>
        <p class="note">
          {{ settings.visibility === 'private'
            ? 'Откроют участники; остальные увидят экран «Запросить доступ». Кого пустить — в «Доступе».'
            : 'Откроет любой — поделитесь ею в чате или письме. Кто ещё может голосовать и предлагать — в «Доступе».' }}
        </p>
        <!-- until the email is confirmed the API answers visitors 403 (EnsureProjectPublished) -->
        <p v-if="auth.me && !auth.me.email_verified" class="board-access-warn">
          Пока почта не подтверждена, ссылка и виджет не работают для посетителей: они увидят «Доска ещё не открыта».
        </p>
        <pre class="board-access-code"><code>{{ boardUrl }}</code></pre>
        <div class="board-access-actions">
          <CopyButton :value="boardUrl" label="Скопировать ссылку" />
          <a :href="boardUrl" target="_blank" rel="noopener" class="btn">Перейти на доску ↗</a>
        </div>
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
import { RouterLink, useRoute } from 'vue-router'
import { type BoardSettings, BoardsApi } from '@/api/boards'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import CopyButton from '@/shared/ui/CopyButton.vue'
import BoardWorkspace from './components/BoardWorkspace.vue'
import SettingsGeneral from './components/SettingsGeneral.vue'
import { boardLoadError } from './composables/boardLoadError'
import { NO_CONFIGURE_TEXT, useLostAccess } from './composables/useLostAccess'
import { useDeleteBoard } from './composables/useDeleteBoard'

// «Общее» — what the board is: its name and address, the link to share, deletion set apart below (useDeleteBoard).
// One of the board's four settings sections (TEST.md п. 42); the router sends the old /settings#categories,
// #statuses, #domains and #access to their new sections. The board store is kept in sync on a rename.
const route = useRoute()
const auth = useAuthStore()
const board = useBoardStore()
const { deleteBoard } = useDeleteBoard()

const slug = computed(() => String(route.params.slug ?? ''))
const settings = ref<BoardSettings | null>(null)
const loading = ref(true)
const loadError = ref('')
// the board lives in this same app, at /<slug>
const boardUrl = computed(() => `${window.location.origin}/${slug.value}`)
// admins open settings too, but deleting the board stays with its owner (the API checks owner_id)
const isOwner = computed(() => auth.me?.projects.find((b) => b.slug === slug.value)?.role === 'owner')

watch(slug, load, { immediate: true })
useLostAccess(slug, () => {
  settings.value = null
  loadError.value = NO_CONFIGURE_TEXT
})

async function load() {
  loading.value = true
  loadError.value = ''
  settings.value = null
  try {
    const res = await BoardsApi.show(slug.value)
    settings.value = res.data
  } catch (e) {
    loadError.value = boardLoadError(e)
  } finally {
    loading.value = false
  }
  // the sections exist only now — scroll to the anchor we arrived with (e.g. /settings#link)
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