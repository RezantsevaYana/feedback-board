<template>
  <AppHeader v-if="withHeader" :extra-menu-items="menuExtras" />
  <main class="container base-page board-page" :class="{ 'no-header': !withHeader }">
    <!-- 404: slug didn't match any board. The brand slant tag + display heading, the typed address as a
         mock URL with the missing slug struck through, then «На главную» / «Мои доски» (if signed in). -->
    <div v-if="board.notFound" class="not-found">
      <span class="not-found-tag slant">404</span>
      <h1>Доска не&nbsp;найдена</h1>
      <p class="not-found-lead">По этой ссылке нет доски. Возможно, её <b>переименовали</b> или в&nbsp;адресе опечатка.</p>
      <div class="not-found-url">
        <span class="not-found-url-label">Адрес</span>
        <code><span class="host">{{ host }}</span>/<span class="slug">{{ slug }}</span></code>
        <span class="not-found-url-warn" aria-hidden="true">!</span>
      </div>
      <p class="not-found-hint">Правильный формат — <code>/название-проекта</code>.</p>
      <div class="not-found-actions">
        <RouterLink to="/" class="btn btn-primary btn-lg slant">На главную</RouterLink>
        <RouterLink v-if="board.me" to="/app/boards" class="btn btn-lg">Мои доски</RouterLink>
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

    <!-- 500 / network / anything that isn't 404 or 403: a giant brand «Упс» with a red indicator dot,
         a short headline, and «Попробовать снова» / «На главную». The retry calls board.open(slug) so it's
         a soft re-fetch, not a page reload — if the glitch cleared, the board slides in without a flash. -->
    <div v-else-if="!board.project && board.loadError" class="load-error">
      <span class="load-error-ups">Упс</span>
      <h1>Доска не&nbsp;загрузилась — сервер ответил ошибкой.</h1>
      <p class="load-error-lead">Мы уже видим это и&nbsp;разбираемся. Попробуйте через минуту.</p>
      <div class="load-error-actions">
        <button type="button" class="btn btn-primary btn-lg slant" @click="board.open(slug)">Попробовать снова</button>
        <RouterLink to="/" class="btn btn-lg">На главную</RouterLink>
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
        <!-- the board switcher now lives in AppHeader above; the sidebar just names the current board -->
        <RouterLink class="proj slant" :to="{ name: 'board', params: { slug } }">{{ board.project.name }}</RouterLink>
        <!-- what the board is and who I am on it, right under its name; only for people with a role on the board:
             a guest just uses the board, «Открытая» means nothing to them (TEST.md п. 39) -->
        <div v-if="board.role" class="board-who">
          <VisibilityBadge :visibility="board.project.visibility" />
          <span class="board-who-role">Вы — {{ ROLE_LABELS[board.role].toLowerCase() }}</span>
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
        <!-- a reader on a closed board only reads: no «Предложить» (the API refuses it too).
             A guest on an open board where the owner turned off guest posts (test.md п. 40): the same CTA slot
             turns into an outline «locked» button — lock icon + «Предложить», click goes to login. One element,
             the familiar shape, the lock says «нужна авторизация». -->
        <button v-if="board.canSubmit" type="button" class="btn btn-primary board-cta" aria-label="Предложить" @click="compose()">
          <span class="cta-roll" aria-hidden="true"><span>Предложить</span><span>Есть идея?</span></span>
        </button>
        <RouterLink
          v-else-if="!board.me"
          :to="{ path: '/app/login', query: { next: route.fullPath } }"
          class="btn board-cta board-cta-locked"
          aria-label="Войдите, чтобы предложить"
          title="Войдите в аккаунт, чтобы оставить идею"
        >
          <AppIcon name="lock" :size="16" />
          <span>Предложить</span>
        </RouterLink>
        <!-- a guest reads, votes and posts without an account; signing in is for the team of an open board,
             through the one login page that brings them back here (a closed board has the gate with its own «Войти»).
             For signed-in users the whole admin / account block lives in AppHeader above — see test.md п. 14, п. 7. -->
        <RouterLink v-if="!board.me && board.canSubmit" :to="{ path: '/app/login', query: { next: route.fullPath } }" class="link-btn team-login">Войти</RouterLink>
        <!-- theme toggle lives at the bottom of the sidebar; the account menu's own «Выйти» is in AppHeader -->
        <ThemeToggle class="board-nav-theme" />
      </nav>

      <div class="board-main">
        <div v-if="refreshing" class="board-progress" role="progressbar" aria-label="Обновляем доску" />
        <TopPosts v-if="board.view.section === 'top'" @open="openPost" />
        <LeaderboardAuthors v-else-if="board.view.section === 'authors'" />
        <WeeklyWinners v-else-if="board.view.section === 'weekly'" @open="openPost" />
        <template v-else>
          <div class="board-head">
            <div class="board-title">
              <h1>Предложения</h1>
              <p v-if="board.team" class="board-keys">
                Перетащите карточку в другой столбец или нажмите на неё, чтобы изменить.
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
              <button type="button" :aria-pressed="board.view.layout === 'kanban'" @click="board.view.layout = 'kanban'"><AppIcon name="columns" />Канбан</button>
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
          <!-- patologic fallback: a board somehow created without statuses (should be impossible via ProjectService,
               but any Eloquent::create bypassing it leaves the Kanban with no columns to draw at all) -->
          <div v-else-if="!board.statuses.length" class="board-empty">
            <h2>Доска ещё не настроена</h2>
            <p v-if="board.configure">У доски нет ни одного статуса, поэтому предложения не отображаются. Добавьте хотя бы один статус, чтобы доска заработала.</p>
            <p v-else>Владелец доски пока не настроил её. Загляните чуть позже.</p>
            <div v-if="board.configure" class="row">
              <button type="button" class="btn" @click="openQuick('statuses')">Открыть настройки статусов</button>
            </div>
          </div>
          <KanbanBoard v-else-if="board.view.layout === 'kanban'" @open="openPost" />
          <PostList v-else @open="openPost" />
        </template>
      </div>
    </div>

    <!-- TODO: закомментили футер — вернёмся. Был quiet strip для голосующего на доске: слант-бренд + клейм
         слева, тихое «Создать свою доску →» справа. Виден, когда board.project загружен (не на 404/скелете/500).
    <footer v-if="board.project" class="board-footer">
      <div class="board-footer-left">
        <RouterLink to="/" class="board-footer-brand slant">Feedback Board</RouterLink>
        <span class="board-footer-claim">— одна доска вместо сообщений в чатах</span>
      </div>
      <RouterLink :to="board.me ? '/app/boards/new' : '/app/register'" class="board-footer-cta">
        Создать свою доску <span class="board-footer-cta-arrow" aria-hidden="true">→</span>
      </RouterLink>
    </footer>
    -->


    <PostModal v-if="board.project && postId" :id="postId" @close="closePost" />
    <SubmitModal v-if="composing && board.project" :project="board.project.slug" :categories="board.categories" :title="composeTitle" :author="board.me ? { name: board.me.name } : null" :hide-author-field="board.project.visibility === 'private'" @close="closeCompose" @sent="onSent" />
    <VotersModal />
    <QuickSettingsModal v-if="quickTab && board.configure" :initial="quickTab" @close="quickTab = null" />
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
import VerifyEmailBanner from '@/shared/components/VerifyEmailBanner.vue'
import VisibilityBadge from '@/shared/components/VisibilityBadge.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'
import SearchComponent from '@/shared/ui/SearchComponent.vue'
import AccessGate from './components/AccessGate.vue'
import BoardFilters from './components/BoardFilters.vue'
import BoardSkeleton from './components/BoardSkeleton.vue'
import { useAuthStore } from '@/store/auth/useAuthStore'
import AppHeader from '@/shared/components/AppHeader.vue'
import ThemeToggle from '@/shared/ui/ThemeToggle.vue'
import FiltersModal from './components/FiltersModal.vue'
import KanbanBoard from './components/KanbanBoard.vue'
import LeaderboardAuthors from './components/LeaderboardAuthors.vue'
import PostList from './components/PostList.vue'
import PostModal from './components/PostModal.vue'
import QuickSettingsModal from './components/QuickSettingsModal.vue'
import SubmitModal from './components/SubmitModal.vue'
import VotersModal from './components/VotersModal.vue'
import TopPosts from './components/TopPosts.vue'
import WeeklyWinners from './components/WeeklyWinners.vue'
import WeeklyWinnersModal from './components/WeeklyWinnersModal.vue'

// /<slug> and /<slug>/posts/<id>: the board with the post card on top (TZ 5.2).
const route = useRoute()
const router = useRouter()
const board = useBoardStore()
const auth = useAuthStore()
const notification = useNotificationStore()

const slug = computed(() => String(route.params.slug ?? ''))
// shown in the «Доска не найдена» address card next to the missing slug
const host = computed(() => location.host)
const postId = computed(() => (route.params.id ? Number(route.params.id) : null))
const composing = ref(false)
// which tab the «Быстрая настройка» modal opens on; null — modal is closed
const quickTab = ref<'statuses' | 'categories' | null>(null)
// the service header (board switcher, account) is for signed-in people; a guest sees only the board,
// and the sticky sidebar then sits under the page's top edge instead of under the header
const withHeader = computed(() => !!board.project && !!board.me)
function openQuick(tab: 'statuses' | 'categories') {
  quickTab.value = tab
}
// admin shortcuts added to the AccountMenu dropdown on the board page: owner/admin only
const menuExtras = computed<MenuItem[]>(() => {
  if (!board.configure) return []
  return [
    { label: 'Быстрая настройка', run: () => openQuick('statuses') },
    { label: 'К настройкам доски', run: () => router.push(`/app/boards/${slug.value}/settings`) },
  ]
})
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
      board.view.category.length > 0,
      board.view.layout === 'list' && board.view.status.length > 0,
      board.team && board.view.priority.length > 0,
      board.team && !board.view.showHidden,
      board.view.sort !== 'new',
    ].filter(Boolean).length,
)
// team mode: every board this user is on is one click away; owner / admin also get the board's settings
// access requests waiting on this board (owner / admin only; /me sends 0 to everyone else)
const pendingRequests = computed(() => board.me?.projects.find((p) => p.slug === slug.value)?.pending_requests ?? 0)
// the role on this board under the name in the account block; «вы вошли» for someone signed in but not on it
const whoChip = computed(() => (board.role ? `роль ${ROLE_LABELS[board.role].toLowerCase()}` : 'вы вошли'))
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

// test.md п. 24: пришли по старому адресу — API вернёт проект под новым slug, адресную строку меняем в SPA
// (кеш vite-роутер, без перезагрузки). Проверяем именно project.slug, потому что это уже актуальное значение
// после резолвера; redirect_slug на эту же цель, но project.slug достаточно.
watch(() => board.project?.slug, (actual) => {
  if (!actual || actual === slug.value) return
  void router.replace({
    name: route.name === 'post' ? 'post' : 'board',
    params: { ...route.params, slug: actual },
    query: route.query,
    hash: route.hash,
  })
})

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
  // AppHeader's account dropdown relies on auth.me; the board route doesn't go through AppShell,
  // so we hydrate the auth store here instead (board.me alone wouldn't populate auth.boardSummaries either).
  void auth.fetchMe().catch(() => undefined)
  document.addEventListener('visibilitychange', onVisibility)
  document.addEventListener('keydown', onSearchKey)
})
onBeforeUnmount(() => {
  board.stopPolling()
  document.removeEventListener('visibilitychange', onVisibility)
  document.removeEventListener('keydown', onSearchKey)
  // leaving the board: drop the noindex tag so it doesn't leak into other routes
  setRobotsNoindex(false)
})
watch(
  () => board.project?.name,
  (name) => (document.title = name ? `${name} · предложения` : 'Feedback Board'),
)
// test.md п. 19: a private board or an open board the owner hid from search engines gets <meta robots noindex>.
// Dynamic robots.txt (42.10.2.4) handles the bulk crawler case; this tag keeps a bot that already has the URL out.
watch(
  () => [board.project?.visibility, board.project?.search_indexable],
  ([visibility, indexable]) => {
    setRobotsNoindex(!!visibility && (visibility === 'private' || indexable === false))
  },
  { immediate: true },
)
function setRobotsNoindex(noindex: boolean): void {
  const existing = document.head.querySelector<HTMLMetaElement>('meta[name="robots"][data-board]')
  if (!noindex) {
    existing?.remove()
    return
  }
  const tag = existing ?? document.createElement('meta')
  tag.setAttribute('name', 'robots')
  tag.setAttribute('content', 'noindex, nofollow')
  tag.dataset.board = ''
  if (!existing) document.head.appendChild(tag)
}
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
