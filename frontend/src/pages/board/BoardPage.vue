<template>
  <main class="container base-page">
    <div v-if="board.notFound" class="center-page">
      <div>
        <h1>Доска не найдена</h1>
        <p class="note">Проверьте адрес: доска открывается по ссылке вида /название-проекта.</p>
      </div>
    </div>

    <div v-else-if="!board.project" class="center-page">
      <p class="note">{{ board.loadError || 'Загружаем доску…' }}</p>
    </div>

    <div v-else class="board-layout">
      <nav class="board-nav" aria-label="Разделы доски">
        <RouterLink class="proj slant" :to="{ name: 'board', params: { slug } }">{{ board.project.name }}</RouterLink>
        <div class="board-sections">
          <button type="button" class="nav-item" :aria-current="board.view.section === 'posts' ? 'page' : undefined" @click="setSection('posts')">
            <AppIcon name="columns" /><span>Предложения</span>
          </button>
          <button type="button" class="nav-item" :aria-current="board.view.section === 'top' ? 'page' : undefined" @click="setSection('top')">
            <AppIcon name="chart" /><span>Топ</span>
          </button>
          <button type="button" class="nav-item" :aria-current="board.view.section === 'authors' ? 'page' : undefined" @click="setSection('authors')">
            <AppIcon name="thumb" /><span>Авторы</span>
          </button>
        </div>
        <button type="button" class="btn btn-primary board-cta" @click="compose()">Предложить</button>
        <div v-if="board.team" class="team-box">
          <span class="chip">режим команды</span>
          <span class="who-mail">{{ board.me?.email }}</span>
          <button type="button" class="link-btn" @click="editingCategories = true">Категории</button>
          <button type="button" class="link-btn" @click="editingStatuses = true">Столбцы</button>
          <span class="team-box-sep" aria-hidden="true" />
          <button type="button" class="link-btn link-btn-danger" @click="askLogout">Выйти</button>
        </div>
        <button v-else type="button" class="link-btn team-login" @click="loggingIn = true">Вход для команды</button>
        <ThemeToggle />
        <!-- tablet and phone: the team box folds into one menu next to the theme switch -->
        <MenuComponent v-if="board.team" :items="teamItems" class="team-menu" :title="board.me?.email" aria-label="Режим команды">Команда</MenuComponent>
      </nav>

      <div class="board-main">
        <TopPosts v-if="board.view.section === 'top'" @open="openPost" />
        <LeaderboardAuthors v-else-if="board.view.section === 'authors'" />
        <template v-else>
          <div class="board-head">
            <div class="board-title">
              <h1>Предложения</h1>
              <p v-if="board.team" class="board-keys">
                Перетащите карточку в другой столбец или нажмите на неё, чтобы изменить.
                Клавиши: <kbd>J</kbd> <kbd>K</kbd> выбор, <kbd>1</kbd>–<kbd>{{ Math.min(board.statuses.length, 9) }}</kbd> перенос в столбец, <kbd>H</kbd> скрыть.
              </p>
              <p v-else>Что предлагают и что уже сделано. Голосуйте за то, что нужно вам.</p>
            </div>
            <div class="board-search">
              <SearchComponent
                ref="searchField"
                v-model="searchInput"
                shortcut="/"
                placeholder="Поиск по доске"
                aria-label="Поиск по доске"
                aria-keyshortcuts="/ Control+K Meta+K"
                maxlength="120"
                @keydown.esc="blurSearch"
              />
            </div>
          </div>

          <div class="filters">
            <div class="seg-icons" role="group" aria-label="Вид">
              <button type="button" :aria-pressed="board.view.layout === 'kanban'" @click="board.view.layout = 'kanban'"><AppIcon name="columns" />Столбцы</button>
              <button type="button" :aria-pressed="board.view.layout === 'list'" @click="board.view.layout = 'list'"><AppIcon name="list" />Список</button>
            </div>
            <!-- phone: the filters live in a bottom sheet behind this button -->
            <button type="button" class="btn filters-toggle" aria-haspopup="dialog" @click="filtersOpen = true">
              Фильтры<span v-if="activeFilters" class="filters-count">{{ activeFilters }}</span>
            </button>
            <span class="toolbar-sep" aria-hidden="true" />
            <BoardFilters />
          </div>

          <div v-if="board.loadError" class="alert" role="alert">{{ board.loadError }}</div>
          <div v-else-if="noResults" class="board-empty">
            <h2>{{ board.view.search ? `По запросу «${board.view.search}» ничего не нашлось` : 'Под эти фильтры ничего не нашлось' }}</h2>
            <p>Попробуйте другое слово или сбросьте фильтры. Если такого ещё никто не предлагал, предложите первым.</p>
            <div class="row">
              <button type="button" class="btn" @click="resetFilters">Сбросить фильтры</button>
              <button type="button" class="btn" @click="composeFromSearch">{{ board.view.search ? `Предложить «${shortSearch}»` : 'Предложить своё' }}</button>
            </div>
          </div>
          <KanbanBoard v-else-if="board.view.layout === 'kanban'" @open="openPost" />
          <PostList v-else @open="openPost" />
        </template>
      </div>
    </div>

    <PostModal v-if="board.project && postId" :id="postId" @close="closePost" />
    <SubmitModal v-if="composing && board.project" :project="board.project.slug" :categories="board.categories" :title="composeTitle" @close="closeCompose" @sent="onSent" />
    <LoginModal v-if="loggingIn" @close="loggingIn = false" />
    <CategoriesModal v-if="editingCategories && board.team" @close="editingCategories = false" />
    <StatusesModal v-if="editingStatuses && board.team" @close="editingStatuses = false" />
    <FiltersModal v-if="filtersOpen" @close="filtersOpen = false" @reset="resetFilters" />
  </main>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Post } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { type Section, useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'
import SearchComponent from '@/shared/ui/SearchComponent.vue'
import BoardFilters from './components/BoardFilters.vue'
import CategoriesModal from './components/CategoriesModal.vue'
import FiltersModal from './components/FiltersModal.vue'
import KanbanBoard from './components/KanbanBoard.vue'
import LeaderboardAuthors from './components/LeaderboardAuthors.vue'
import LoginModal from './components/LoginModal.vue'
import PostList from './components/PostList.vue'
import PostModal from './components/PostModal.vue'
import StatusesModal from './components/StatusesModal.vue'
import SubmitModal from './components/SubmitModal.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'
import TopPosts from './components/TopPosts.vue'

// /<slug> and /<slug>/posts/<id>: the board with the post card on top (TZ 5.2).
const route = useRoute()
const router = useRouter()
const board = useBoardStore()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const slug = computed(() => String(route.params.slug ?? ''))
const postId = computed(() => (route.params.id ? Number(route.params.id) : null))
const composing = ref(false)
const loggingIn = ref(false)
const editingCategories = ref(false)
const editingStatuses = ref(false)
// «Предложить», optionally starting from what was searched for and not found
const composeTitle = ref('')
function compose(title = '') {
  composeTitle.value = title
  composing.value = true
}
function closeCompose() {
  composing.value = false
  composeTitle.value = ''
}
// a search or filter that found nothing gets one message instead of empty columns
const noResults = computed(() => !board.loading && board.filtered && board.posts.length === 0)
const shortSearch = computed(() => (board.view.search.length > 28 ? `${board.view.search.slice(0, 27)}…` : board.view.search))
function composeFromSearch() {
  compose(board.view.search)
}
function resetFilters() {
  searchInput.value = ''
  board.resetFilters()
}
// phone: filters sit in a bottom sheet behind «Фильтры · N»
const filtersOpen = ref(false)
const activeFilters = computed(
  () =>
    [
      board.view.category !== '',
      board.view.layout === 'list' && board.view.status !== '',
      board.team && board.view.priority !== '',
      board.team && !board.view.showHidden,
      board.view.sort !== 'new',
    ].filter(Boolean).length,
)
const teamItems = computed<MenuItem[]>(() => [
  { label: 'Категории', run: () => (editingCategories.value = true) },
  { label: 'Столбцы', run: () => (editingStatuses.value = true) },
  { label: 'Выйти', danger: true, separated: true, run: askLogout },
])

// Локальная модель для поля поиска: каждый ввод — тик дебаунса в 300мс,
// чтобы не стрелять запросом на каждую букву. Основной watcher в самом сторе
// уже перезагружает список, когда board.view.search меняется.
const searchInput = ref(board.view.search)
let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(searchInput, (v) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => (board.view.search = v.trim()), 300)
})


watch(slug, (s) => board.open(s), { immediate: true })

// «/» or ⌘K / Ctrl+K puts the cursor into the search (by `code`, so «/» works in the Russian layout too);
// Esc in the field leaves it, so J / K work again
const searchField = ref<InstanceType<typeof SearchComponent> | null>(null)
function onSearchKey(e: KeyboardEvent) {
  const typing = (e.target as HTMLElement | null)?.closest('input, textarea, select, [contenteditable]')
  const slash = !typing && !e.metaKey && !e.ctrlKey && !e.altKey && (e.key === '/' || e.code === 'Slash')
  const cmdK = (e.metaKey || e.ctrlKey) && e.code === 'KeyK'
  if (!slash && !cmdK) return
  if (document.body.classList.contains('locked') || board.view.section !== 'posts') return
  e.preventDefault()
  searchField.value?.focus()
}
function blurSearch(e: KeyboardEvent) {
  ;(e.target as HTMLElement).blur()
}

// Пулинг новых идей и голосов — пока вкладка открыта. Возврат из фона
// сразу тянет свежие данные, чтобы не ждать следующего тика.
function onVisibility() {
  if (!document.hidden) board.refresh(true)
}
onMounted(() => {
  board.startPolling()
  document.addEventListener('visibilitychange', onVisibility)
  document.addEventListener('keydown', onSearchKey)
})
onBeforeUnmount(() => {
  board.stopPolling()
  document.removeEventListener('visibilitychange', onVisibility)
  document.removeEventListener('keydown', onSearchKey)
})
watch(
  () => board.project?.name,
  (name) => (document.title = name ? `${name} · предложения` : 'Feedback Board'),
)
// any change of what is shown reloads from the server; team mode switches endpoints
watch(
  () => [board.view.section, board.view.layout, board.view.category, board.view.status, board.view.search, board.view.sort, board.view.priority, board.view.showHidden, board.view.leaderboardScope, board.view.leaderboardMetric, board.team],
  () => board.project && board.refresh(),
)

function openPost(post: Post) {
  router.push({ name: 'post', params: { slug: slug.value, id: post.id } })
}
function closePost() {
  router.push({ name: 'board', params: { slug: slug.value } })
}
function onSent(post: Post) {
  closeCompose()
  notification.success('Предложение опубликовано')
  board.refresh()
  openPost(post)
}
function setSection(section: Section) {
  board.view.section = section
}

async function askLogout() {
  const ok = await confirm.ask({
    title: 'Выйти из режима команды?',
    message: `${board.me?.email ?? 'Админ'} — выйти? Чтобы снова зайти, понадобится пароль.`,
    confirmText: 'Выйти',
    danger: true,
  })
  if (ok) board.logout()
}
</script>
