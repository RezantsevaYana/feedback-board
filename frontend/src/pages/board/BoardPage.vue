<template>
  <main class="container base-page">
    <div v-if="board.notFound" class="center-page">
      <div>
        <h1>Доска не найдена</h1>
        <p class="note">Проверьте адрес: доска открывается по ссылке вида /название-проекта.</p>
      </div>
    </div>

    <!-- 403 from the API: the owner hasn't confirmed the email yet; the owner gets in by logging in -->
    <div v-else-if="board.unpublished" class="center-page">
      <div>
        <h1>Доска ещё не открыта</h1>
        <p class="note">Её владелец заканчивает настройку. Загляните чуть позже.</p>
        <p v-if="board.me" class="note">Вы вошли как {{ board.me.email }}, а доска принадлежит другому аккаунту.</p>
        <p v-else class="note">Это ваша доска? <RouterLink :to="{ path: '/app/login', query: { next: route.fullPath } }">Войдите</RouterLink>, чтобы её увидеть.</p>
      </div>
    </div>

    <!-- 403 reason=login_required (guest) or no_access (signed in, not on the board): the gate with «Запросить доступ» -->
    <div v-else-if="board.privateLocked || board.noAccess" class="center-page access-gate-page">
      <AccessGate :key="slug" :slug="slug" :name="board.gateName" :me="board.noAccess ? board.me : null" @logout="board.logout()" />
    </div>

    <div v-else-if="!board.project && board.loadError" class="center-page">
      <div>
        <p class="note">Не получилось загрузить доску: {{ board.loadError }}</p>
        <button type="button" class="btn" @click="board.open(slug)">Повторить</button>
      </div>
    </div>

    <!-- first load: the page's own frame in placeholders, so nothing jumps when the board arrives -->
    <div v-else-if="!board.project" class="board-layout" role="status">
      <span class="visually-hidden">Загружаем доску…</span>
      <div class="board-nav sk-stack" aria-hidden="true">
        <span class="sk sk-tag" />
        <span class="sk sk-nav" />
        <span class="sk sk-nav" />
        <span class="sk sk-nav" />
        <span class="sk sk-btn" />
      </div>
      <div class="board-main" aria-hidden="true">
        <div class="board-head">
          <div class="board-title sk-stack">
            <span class="sk sk-h1" />
            <span class="sk sk-line sk-short" />
          </div>
          <span class="sk sk-input board-search" />
        </div>
        <div class="filters">
          <span class="sk sk-votes" />
          <span class="sk sk-chip" />
          <span class="sk sk-chip" />
        </div>
        <BoardSkeleton :layout="board.view.layout" />
      </div>
    </div>

    <div v-else class="board-layout">
      <nav class="board-nav" aria-label="Разделы доски">
        <!-- team mode with several boards: the brand tag opens the switcher instead of reloading this board -->
        <BoardSwitcher v-if="switchBoards.length > 1" class="proj slant" :boards="switchBoards" :current="slug" :label="board.project.name" />
        <RouterLink v-else class="proj slant" :to="{ name: 'board', params: { slug } }">{{ board.project.name }}</RouterLink>
        <!-- what the board is and who I am on it, right under its name -->
        <div class="board-who">
          <VisibilityBadge :visibility="board.project.visibility" />
          <span v-if="board.role" class="board-who-role">Вы — {{ ROLE_LABELS[board.role].toLowerCase() }}</span>
        </div>
        <div class="board-sections">
          <button
            v-for="s in primarySections"
            :key="s.id"
            type="button"
            class="nav-item"
            :aria-current="board.view.section === s.id ? 'page' : undefined"
            @click="setSection(s.id)"
          >
            <AppIcon :name="s.icon" /><span>{{ s.label }}</span>
          </button>
          <!-- Desktop sidebar: overflow sections live here too; segmented control on tablet/mobile hides them in favour of the "Ещё" menu below -->
          <button
            v-for="s in overflowSections"
            :key="s.id"
            type="button"
            class="nav-item nav-item-overflow"
            :aria-current="board.view.section === s.id ? 'page' : undefined"
            @click="setSection(s.id)"
          >
            <AppIcon :name="s.icon" /><span>{{ s.label }}</span>
          </button>
          <MenuComponent
            v-if="overflowSections.length"
            :items="overflowItems"
            class="sections-more"
            :class="{ active: overflowActive }"
            aria-label="Ещё разделы"
          >
            <span>Ещё</span><AppIcon name="chevron" />
          </MenuComponent>
        </div>
        <!-- hover rolls the label up to a question; screen readers always hear «Предложить» -->
        <!-- a reader on a closed board only reads: no «Предложить» (the API refuses it too) -->
        <button v-if="!board.readOnly" type="button" class="btn btn-primary board-cta" aria-label="Предложить" @click="compose()">
          <span class="cta-roll" aria-hidden="true"><span>Предложить</span><span>Есть идея?</span></span>
        </button>
        <!-- signed in: the board's tools (owner / admin) and the same account block as in the cabinet's menu,
             so «Мои доски», «Настройки аккаунта» and «Выйти» are always in sight. Tablet and phone: both go
             into the panel behind «Меню» -->
        <template v-if="board.me">
          <BoardTools v-if="board.configure" :slug="slug" :pending="pendingRequests" class="board-tools-side" @categories="editingCategories = true" @statuses="editingStatuses = true" />
          <SidebarAccount :name="board.me.name" :email="board.me.email" :chip="whoChip" show-boards class="board-account" />
        </template>
        <!-- a guest reads, votes and posts without an account; signing in is for the team of an open board,
             through the one login page that brings them back here (a closed board has the gate with its own «Войти») -->
        <RouterLink v-else :to="{ path: '/app/login', query: { next: route.fullPath } }" class="link-btn team-login">Войти</RouterLink>
        <!-- signed in, on tablet and phone the theme moves into the drawer, so the top row fits -->
        <ThemeToggle :class="{ 'board-theme-side': board.me }" />
        <button v-if="board.me" type="button" class="btn team-menu-btn" aria-label="Меню" :aria-expanded="menuOpen" @click="menuOpen = true">
          <BurgerIcon /><span class="team-menu-label">Меню</span>
        </button>
      </nav>

      <div class="board-main">
        <div v-if="refreshing" class="board-progress" role="progressbar" aria-label="Обновляем доску" />
        <!-- the owner on an unpublished board (everyone else gets «Доска ещё не открыта») -->
        <VerifyEmailBanner v-if="!board.project.published && board.me" :email="board.me.email" class="board-verify" />
        <TopPosts v-if="board.view.section === 'top'" @open="openPost" />
        <LeaderboardAuthors v-else-if="board.view.section === 'authors'" />
        <WeeklyWinners v-else-if="board.view.section === 'weekly'" @open="openPost" />
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
            <Transition name="filters-reset">
              <button v-if="activeFilters" type="button" class="icon-btn filters-reset" aria-label="Сбросить фильтры" title="Сбросить фильтры" @click="resetFilters"><AppIcon name="close" /></button>
            </Transition>
            <span class="toolbar-sep" aria-hidden="true" />
            <BoardFilters />
          </div>

          <div v-if="board.loadError" class="alert" role="alert">
            {{ board.loadError }}
            <button type="button" class="link-btn" @click="board.refresh()">Повторить</button>
          </div>
          <BoardSkeleton v-else-if="!board.postsLoaded || (board.loading && !board.posts.length)" :layout="board.view.layout" />
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
    <!-- tablet and phone: the board's tools and the account, behind «Меню» -->
    <NavDrawer v-if="menuOpen && board.me" ref="drawer" label="Меню доски" anchor=".team-menu-btn" @close="onMenuClosed">
      <div class="board-drawer">
        <p class="board-drawer-title">{{ board.project?.name }}</p>
        <BoardTools v-if="board.configure" :slug="slug" :pending="pendingRequests" @categories="openFromMenu('categories')" @statuses="openFromMenu('statuses')" />
        <SidebarAccount :name="board.me.name" :email="board.me.email" :chip="whoChip" show-boards>
          <template #after-out><ThemeToggle /></template>
        </SidebarAccount>
      </div>
    </NavDrawer>
    <SubmitModal v-if="composing && board.project" :project="board.project.slug" :categories="board.categories" :title="composeTitle" :author="board.me ? { name: board.me.name } : null" :hide-author-field="board.project.visibility === 'private'" @close="closeCompose" @sent="onSent" />
    <VotersModal />
    <CategoriesModal v-if="editingCategories && board.configure" @close="editingCategories = false" />
    <StatusesModal v-if="editingStatuses && board.configure" @close="editingStatuses = false" />
    <FiltersModal v-if="filtersOpen" @close="filtersOpen = false" @reset="resetFilters" />
    <WeeklyWinnersModal v-if="weeklyModalOpen" @close="dismissWeeklyModal" @open-section="openWeeklySection" />
  </main>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { Post } from '@/api/types'
import { ROLE_LABELS } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { type Section, useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import BoardSwitcher from '@/shared/components/BoardSwitcher.vue'
import VerifyEmailBanner from '@/shared/components/VerifyEmailBanner.vue'
import VisibilityBadge from '@/shared/components/VisibilityBadge.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'
import SearchComponent from '@/shared/ui/SearchComponent.vue'
import AccessGate from './components/AccessGate.vue'
import BoardFilters from './components/BoardFilters.vue'
import BoardSkeleton from './components/BoardSkeleton.vue'
import CategoriesModal from './components/CategoriesModal.vue'
import FiltersModal from './components/FiltersModal.vue'
import KanbanBoard from './components/KanbanBoard.vue'
import LeaderboardAuthors from './components/LeaderboardAuthors.vue'
import PostList from './components/PostList.vue'
import PostModal from './components/PostModal.vue'
import StatusesModal from './components/StatusesModal.vue'
import SubmitModal from './components/SubmitModal.vue'
import VotersModal from './components/VotersModal.vue'
import BurgerIcon from '@/shared/ui/BurgerIcon.vue'
import NavDrawer from '@/shared/ui/NavDrawer.vue'
import SidebarAccount from '@/shared/components/SidebarAccount.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'
import BoardTools from './components/BoardTools.vue'
import TopPosts from './components/TopPosts.vue'
import WeeklyWinners from './components/WeeklyWinners.vue'
import WeeklyWinnersModal from './components/WeeklyWinnersModal.vue'

// /<slug> and /<slug>/posts/<id>: the board with the post card on top (TZ 5.2).
const route = useRoute()
const router = useRouter()
const board = useBoardStore()
const notification = useNotificationStore()

const slug = computed(() => String(route.params.slug ?? ''))
const postId = computed(() => (route.params.id ? Number(route.params.id) : null))
const composing = ref(false)
const editingCategories = ref(false)
const editingStatuses = ref(false)
// «Предложить», optionally starting from what was searched for and not found
const composeTitle = ref('')
function compose(title = '') {
  if (board.readOnly) return
  composeTitle.value = title
  composing.value = true
}
function closeCompose() {
  composing.value = false
  composeTitle.value = ''
}
// data already on screen is reloading (filter, view, sort): a thin bar on top instead of placeholders
const refreshing = computed(() => {
  if (!board.loading || board.loadingMore) return false
  if (board.view.section === 'top') return board.ranking.length > 0
  if (board.view.section === 'authors') return board.leaderboard.length > 0
  if (board.view.section === 'weekly') return board.weeklyWinners.length > 0
  return board.posts.length > 0
})
// a search or filter that found nothing gets one message instead of empty columns
const noResults = computed(() => !board.loading && board.filtered && board.posts.length === 0)
const shortSearch = computed(() => (board.view.search.length > 28 ? `${board.view.search.slice(0, 27)}…` : board.view.search))
function composeFromSearch() {
  compose(board.view.search)
}
function resetFilters() {
  searchInput.value = ''
  board.resetFilters()
  board.view.sort = 'new'
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
// team mode: every board this user is on is one click away; owner / admin also get the board's settings
const switchBoards = computed(() => (board.team ? (board.me?.projects ?? []) : []))
// access requests waiting on this board (owner / admin only; /me sends 0 to everyone else)
const pendingRequests = computed(() => board.me?.projects.find((p) => p.slug === slug.value)?.pending_requests ?? 0)
// the role on this board under the name in the account block; «вы вошли» for someone signed in but not on it
const whoChip = computed(() => (board.role ? `роль ${ROLE_LABELS[board.role].toLowerCase()}` : 'вы вошли'))
// tablet and phone: «Меню» opens the drawer with the tools and the account
const menuOpen = ref(false)
const drawer = ref<InstanceType<typeof NavDrawer> | null>(null)
// an editor picked in the drawer opens once the drawer has gone: both lock the page and trap focus
let afterMenu: (() => void) | null = null
function openFromMenu(editor: 'categories' | 'statuses') {
  afterMenu = () => (editor === 'categories' ? (editingCategories.value = true) : (editingStatuses.value = true))
  drawer.value?.close()
}
function onMenuClosed() {
  menuOpen.value = false
  afterMenu?.()
  afterMenu = null
}

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
  // the team's post card is an edit form — after «Предложить» they expect the post on the board, not its editor
  if (!board.team) openPost(post)
}
function setSection(section: Section) {
  board.view.section = section
}

// Разделы доски — data-driven, чтобы потом подложить сюда админский конфиг
// (видимость/порядок/primary vs secondary per project). primary — всегда видны;
// остальные уходят в выпадашку «Ещё» на tablet/mobile.
type SectionConfig = { id: Section; label: string; icon: 'columns' | 'chart' | 'thumb'; primary: boolean }
const SECTIONS: readonly SectionConfig[] = [
  { id: 'posts', label: 'Предложения', icon: 'columns', primary: true },
  { id: 'top', label: 'Топ', icon: 'chart', primary: true },
  { id: 'authors', label: 'Авторы', icon: 'thumb', primary: false },
  { id: 'weekly', label: 'Хиты недели', icon: 'chart', primary: false },
]
const primarySections = computed(() => SECTIONS.filter((s) => s.primary))
const overflowSections = computed(() => SECTIONS.filter((s) => !s.primary))
const overflowItems = computed<MenuItem[]>(() =>
  overflowSections.value.map((s) => ({ label: s.label, run: () => setSection(s.id) })),
)
// Активная секция оказалась в выпадашке — подсветим кнопку «Ещё».
const overflowActive = computed(() => overflowSections.value.some((s) => s.id === board.view.section))

// Модалка «Пост недели» показывается один раз в неделю на проект: ключ в
// localStorage привязан к дате понедельника текущей ISO-недели, поэтому с
// началом новой недели модалка снова всплывёт один раз. Пустую неделю (все
// категории без постов) не показываем — незачем хвастаться пустотой.
const weeklyModalOpen = ref(false)
function currentWeekKey(): string {
  const d = new Date()
  const day = d.getDay() || 7
  d.setHours(0, 0, 0, 0)
  d.setDate(d.getDate() - (day - 1))
  return d.toISOString().slice(0, 10)
}
function weeklyStorageKey(slug: string): string {
  return `fb-weekly-seen-${slug}-${currentWeekKey()}`
}
async function maybeShowWeeklyModal(slug: string) {
  try {
    if (localStorage.getItem(weeklyStorageKey(slug))) return
  } catch {
    return
  }
  await board.loadWeeklyWinners(true)
  if (board.weeklyWinners.some((e) => e.post)) weeklyModalOpen.value = true
}
function dismissWeeklyModal() {
  weeklyModalOpen.value = false
  const slug = board.project?.slug
  if (!slug) return
  try {
    localStorage.setItem(weeklyStorageKey(slug), '1')
  } catch {
    // storage недоступен — следующий визит снова покажет модалку
  }
}
function openWeeklySection() {
  dismissWeeklyModal()
  setSection('weekly')
}
watch(
  () => board.project?.slug,
  (slug) => {
    if (slug) maybeShowWeeklyModal(slug)
  },
)
</script>
