<template>
  <BoardWorkspace :slug="slug" page="settings">
    <p v-if="loading" class="note">Загружаем…</p>

    <template v-else-if="settings">
      <section id="general" class="board-settings-section general-settings">
        <SettingsGeneral :settings="settings" @saved="onSaved" />

        <section id="link" class="general-settings-link">
          <h2>Ссылка на доску</h2>
          <div class="general-settings-share">
            <code :title="boardUrl">{{ boardUrl }}</code>
            <CopyButton :value="boardUrl" label="Скопировать" />
            <a :href="boardUrl" target="_blank" rel="noopener" class="btn">Открыть ↗</a>
          </div>
          <p class="note">
            {{ settings.visibility === 'private'
              ? 'Откроют участники; остальные увидят экран «Запросить доступ». Кого пустить — в «Доступе».'
              : 'Откроет любой — поделитесь ею в чате или письме. Кто ещё может голосовать и предлагать — в «Доступе».' }}
          </p>
          <p v-if="auth.me && !auth.me.email_verified" class="board-access-warn">
            Пока почта не подтверждена, ссылка и виджет не работают для посетителей: они увидят «Доска ещё не открыта».
          </p>
        </section>
      </section>

      <section v-if="isOwner" class="board-settings-section general-settings-danger">
        <h2>Удалить доску</h2>
        <p class="note">Доска уйдёт в корзину, из списка досок её можно будет восстановить.</p>
        <div>
          <button type="button" class="btn" @click="deleteBoard(settings.slug, settings.name)">Удалить доску</button>
        </div>
      </section>
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
const router = useRouter()
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
  const prev = settings.value
  settings.value = next
  if (!prev) return
  if (next.name !== prev.name) {
    auth.renameBoard(prev.slug, next.name)
    if (board.project?.slug === prev.slug) board.project.name = next.name
  }
  // test.md п. 24: slug изменился — обновляем кеши и адресную строку. Старый адрес будет
  // продолжать работать через project_slug_history и redirect в публичном резолвере.
  if (next.slug !== prev.slug) {
    auth.renameBoardSlug(prev.slug, next.slug)
    if (board.project?.slug === prev.slug) board.project.slug = next.slug
    void router.replace({ name: 'board-settings', params: { slug: next.slug }, hash: route.hash })
  }
}
</script>
<style lang="scss">
@use '@/scss/utils' as *;

.general-settings,
.general-settings-danger {
  min-width: 0;
  max-width: 760px;
  gap: 14px;
  .note { margin: 0; }
}
.general-settings {
  .settings-general { gap: 14px; }
  .field { margin: 0; min-width: 0; }
  .settings-actions { justify-content: start; }
  .input[readonly] { background: var(--surface); color: var(--fg); }
  .input-group input[readonly] { font-family: var(--font-mono); }
}
.general-settings-link {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 10px;
  padding-top: 14px;
  border-top: 1px solid var(--line);
  min-width: 0;
  scroll-margin-top: 80px;
  > h2 { font-size: 15px; }
}
.general-settings-share {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 6px 6px 14px;
  border-radius: var(--radius);
  background: var(--surface-2);
  > code {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-family: var(--font-mono);
    font-size: 14px;
  }
  > .btn { flex: none; }
}
.general-settings-danger {
  border-color: color-mix(in srgb, var(--bug) 30%, var(--line));
  > h2 { color: var(--bug); font-size: 18px; }
  .btn { color: var(--bug); border-color: color-mix(in srgb, var(--bug) 40%, var(--line)); }
}
@media screen and (max-width: $mobile) {
  .general-settings {
    .general-settings-link { @include bleed; gap: 8px; }
    .settings-actions .btn { flex: none; }
    .input, .btn { min-height: var(--settings-control-size, 44px); }
    .input { font-size: 16px; }
  }
  .general-settings-share {
    flex-wrap: wrap;
    padding: 10px 12px;
    > code { flex-basis: 100%; }
  }
  .general-settings-danger {
    border-top: 1px solid var(--line);
    padding-top: 16px;
    .btn { min-height: var(--settings-control-size, 44px); }
  }
}
</style>
