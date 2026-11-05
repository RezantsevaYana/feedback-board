import { computed, reactive, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { AuthApi } from '@/api/auth'
import { CategoriesApi } from '@/api/categories'
import { ApiError, errorMessage } from '@/api/client'
import { LeaderboardApi, type LeaderboardEntry, type LeaderboardMetric, type LeaderboardScope } from '@/api/leaderboard'
import { type PostPatch, PostsApi } from '@/api/posts'
import { ProjectsApi } from '@/api/projects'
import { StatusesApi } from '@/api/statuses'
import type { Category, PaletteColor, Me, Post, PostPriority, Project, Status, VoteState, VoteValue } from '@/api/types'
import { WeeklyWinnersApi, type WeeklyWinnerEntry, type WeeklyWinnersMeta } from '@/api/weeklyWinners'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useVotersStore } from '@/store/modals/useVotersStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { canConfigure, canTriage } from '@/shared/utils/roles'

export type Section = 'posts' | 'top' | 'authors' | 'weekly'
export type Layout = 'kanban' | 'list'
export type Sort = 'top' | 'new' | 'priority'

/** Columns show everything at once (MVP volumes); the list pages by 20 (TZ 5.2). */
const KANBAN_LIMIT = 100
const LIST_PAGE = 20

/** Popularity = likes minus dislikes. */
export const score = (p: Post) => p.likes_count - p.dislikes_count

export const useBoardStore = defineStore('board', () => {
  const notification = useNotificationStore()

  const project = ref<Project | null>(null)
  const notFound = ref(false)
  /** 403 from EnsureProjectPublished: the owner hasn't confirmed the email yet, the board is closed to visitors. */
  const unpublished = ref(false)
  /** 403 with reason=login_required — a private board, visitor isn't signed in; show the login prompt. */
  const privateLocked = ref(false)
  /** 403 with reason=no_access — signed-in, but not on this private board. */
  const noAccess = ref(false)
  /** The private board's name from the 403, so the gate can say which board it is; the board itself stays hidden. */
  const gateName = ref('')
  const me = ref<Me | null>(null)
  // One session for the whole app: signing in or out on /app/login (another account in the same tab), or a fresh /me
  // in the cabinet, reaches the board too. open() skips an already loaded board, so without this the board kept
  // the previous account — a guest's screen, or someone else's buttons that ended in 403.
  const auth = useAuthStore()
  watch(() => auth.me, (next) => (me.value = next))
  /** The logged-in user's role on this board; null for guests and outsiders. */
  const role = computed(() => me.value?.projects.find((p) => p.slug === project.value?.slug)?.role ?? null)
  /** Team mode: owner / admin / manager on this board (the API checks it again on every call). */
  const team = computed(() => canTriage(role.value))
  /** Categories, columns and board settings: owner / admin. */
  const configure = computed(() => canConfigure(role.value))
  // Читатель only reads on a closed board; on an open one they're a guest by the link like anyone (the API agrees)
  const readOnly = computed(() => role.value === 'reader' && project.value?.visibility === 'private')
  const categories = computed<Category[]>(() => project.value?.categories ?? [])
  const statuses = computed<Status[]>(() => project.value?.statuses ?? [])
  const defaultStatus = computed<Status | undefined>(() => statuses.value.find((s) => s.slug === 'new'))

  // На мобиле (≤767) по умолчанию — список: канбан-колонки съедают весь экран.
  const defaultLayout: Layout =
    typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches ? 'list' : 'kanban'

  const view = reactive({
    section: 'posts' as Section,
    layout: defaultLayout,
    category: '' as '' | number | 'none',
    status: '' as '' | number,
    search: '',
    sort: 'new' as Sort,
    topScope: 'open' as 'open' | 'all',
    leaderboardScope: 'all' as LeaderboardScope,
    leaderboardMetric: 'posts' as LeaderboardMetric,
    // team only
    priority: '' as '' | PostPriority | 'none',
    showHidden: true,
  })

  /** Something narrows the board (search, category, status, priority, hidden posts left out). */
  const filtered = computed(
    () =>
      !!view.search.trim() ||
      view.category !== '' ||
      (view.layout === 'list' && view.status !== '') ||
      (team.value && (view.priority !== '' || !view.showHidden)),
  )
  function resetFilters() {
    Object.assign(view, { search: '', category: '', status: '', priority: '', showHidden: true })
  }

  const posts = ref<Post[]>([])
  const page = ref(1)
  const lastPage = ref(1)
  // the list view: how many posts match in all, and how many of them are still in «Новое» (they come first)
  const postsTotal = ref(0)
  const newCount = ref<number | null>(null)
  const loading = ref(false)
  /** The first page of posts has arrived: until then the board shows skeletons, not empty columns. */
  const postsLoaded = ref(false)
  /** «Показать ещё» is loading the next page (its button spins; no bar over the whole board). */
  const loadingMore = ref(false)
  const loadError = ref('')
  const topPosts = ref<Post[]>([])
  const leaderboard = ref<LeaderboardEntry[]>([])
  const weeklyWinners = ref<WeeklyWinnerEntry[]>([])
  const weeklyWinnersMeta = ref<WeeklyWinnersMeta | null>(null)

  async function open(slug: string) {
    if (project.value?.slug === slug) return
    notFound.value = false
    unpublished.value = false
    privateLocked.value = false
    noAccess.value = false
    gateName.value = ''
    loadError.value = ''
    project.value = null
    postsLoaded.value = false
    const user = AuthApi.me().then((r) => r.data).catch(() => null)
    try {
      const [p, u] = await Promise.all([ProjectsApi.get(slug), user])
      project.value = p.data
      me.value = u
      await refresh()
    } catch (e) {
      if (e instanceof ApiError && e.status === 404) {
        notFound.value = true
        // on 404 we still need `me` so the «Доска не найдена» screen can show «Мои доски» to signed-in admins
        me.value = await user
      } else if (e instanceof ApiError && e.status === 403) {
        // the EnsureProjectPublished middleware carries a stable reason so the SPA can pick the right screen
        me.value = await user
        const gate = e.body.board as { name?: unknown } | undefined
        gateName.value = typeof gate?.name === 'string' ? gate.name : ''
        switch (e.reason) {
          case 'login_required':
            privateLocked.value = true
            break
          case 'no_access':
            noAccess.value = true
            break
          case 'owner_unverified':
          default:
            unpublished.value = true
        }
      } else loadError.value = errorMessage(e)
    }
  }

  /** The role may have changed in another window: read /me again, team mode and the tools follow it. */
  async function refreshMe() {
    if (!me.value) return
    me.value = await AuthApi.me().then((r) => r.data).catch(() => null)
  }

  const currentSlug = () => project.value?.slug ?? ''

  async function loadPosts(append = false, silent = false) {
    if (!project.value) return
    const nextPage = append ? page.value + 1 : 1
    if (!silent) {
      loading.value = true
      loadingMore.value = append
      loadError.value = ''
    }
    try {
      const query = {
        category: view.category,
        status_id: view.layout === 'list' ? view.status : '',
        q: view.search.trim(),
        sort: !team.value && view.sort === 'priority' ? 'top' : view.sort,
        // the list view without a column filter: «Новое» first, a line before the rest (PostList)
        new_first: view.layout === 'list' && !view.status ? 1 : undefined,
        page: nextPage,
        per_page: view.layout === 'kanban' ? KANBAN_LIMIT : LIST_PAGE,
      }
      const res = team.value
        ? await PostsApi.adminList(currentSlug(), { ...query, priority: view.priority, hidden: view.showHidden ? 'include' : 'exclude' })
        : await PostsApi.list(currentSlug(), query)
      posts.value = append ? [...posts.value, ...res.data] : res.data
      postsLoaded.value = true
      page.value = res.meta.current_page
      lastPage.value = res.meta.last_page
      postsTotal.value = res.meta.total
      newCount.value = res.new_count ?? null
    } catch (e) {
      if (!silent) loadError.value = errorMessage(e)
    } finally {
      if (!silent) {
        loading.value = false
        loadingMore.value = false
      }
    }
  }

  async function loadTop(silent = false) {
    if (!project.value) return
    if (!silent) {
      loading.value = true
      loadError.value = ''
    }
    try {
      const res = await PostsApi.list(currentSlug(), { category: view.category, sort: 'top', per_page: KANBAN_LIMIT })
      topPosts.value = res.data
    } catch (e) {
      if (!silent) loadError.value = errorMessage(e)
    } finally {
      if (!silent) loading.value = false
    }
  }

  async function loadLeaderboard(silent = false) {
    if (!project.value) return
    if (!silent) {
      loading.value = true
      loadError.value = ''
    }
    try {
      const res = await LeaderboardApi.get(currentSlug(), {
        scope: view.leaderboardScope,
        metric: view.leaderboardMetric,
      })
      leaderboard.value = res.data
    } catch (e) {
      if (!silent) loadError.value = errorMessage(e)
    } finally {
      if (!silent) loading.value = false
    }
  }

  async function loadWeeklyWinners(silent = false) {
    if (!project.value) return
    if (!silent) {
      loading.value = true
      loadError.value = ''
    }
    try {
      const res = await WeeklyWinnersApi.get(currentSlug())
      weeklyWinners.value = res.data
      weeklyWinnersMeta.value = res.meta
    } catch (e) {
      if (!silent) loadError.value = errorMessage(e)
    } finally {
      if (!silent) loading.value = false
    }
  }

  /** Ranking by likes minus dislikes; equal score shares a place, the earlier post goes first. Rejected ones do not compete. */
  const ranking = computed(() => {
    const list = topPosts.value
      .filter((p) => p.status.slug !== 'rejected' && (view.topScope === 'all' || p.status.slug !== 'done'))
      .sort((a, b) => score(b) - score(a) || a.created_at.localeCompare(b.created_at))
    let place = 0
    return list.map((post, i) => {
      const prev = list[i - 1]
      if (!prev || score(post) !== score(prev)) place = i + 1
      return { post, place }
    })
  })

  function refresh(silent = false) {
    switch (view.section) {
      case 'top': return loadTop(silent)
      case 'authors': return loadLeaderboard(silent)
      case 'weekly': return loadWeeklyWinners(silent)
      default: return loadPosts(false, silent)
    }
  }

  // Пулинг: каждые POLL_INTERVAL мс тихо тянем первую страницу текущего вида,
  // чтобы новые идеи и свежие голоса появлялись без перезагрузки. Останавливаемся,
  // если вкладка в фоне, запрос уже идёт, или пользователь листанул дальше первой
  // страницы — иначе список скакал бы ему обратно наверх.
  const POLL_INTERVAL = 15000
  let pollTimer: ReturnType<typeof setInterval> | null = null
  function poll() {
    if (!project.value || document.hidden || loading.value) return
    if (view.section === 'posts' && page.value > 1) return
    refresh(true)
  }
  function startPolling(intervalMs = POLL_INTERVAL) {
    stopPolling()
    pollTimer = setInterval(poll, intervalMs)
  }
  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  /** The same post can sit in the board list, the ranking and an open card: keep every copy in sync. */
  function patchLocal(id: number, patch: Partial<Post>) {
    for (const list of [posts.value, topPosts.value]) {
      const post = list.find((p) => p.id === id)
      if (post) Object.assign(post, patch)
    }
  }

  /** Click on like / dislike: a second click on the same one cancels, the other one switches. */
  async function vote(post: Post, value: VoteValue) {
    const was: VoteState = { likes_count: post.likes_count, dislikes_count: post.dislikes_count, my_vote: post.my_vote }
    const cancel = post.my_vote === value
    const next = { ...was, my_vote: cancel ? null : value }
    if (was.my_vote === 'like') next.likes_count--
    if (was.my_vote === 'dislike') next.dislikes_count--
    if (!cancel && value === 'like') next.likes_count++
    if (!cancel && value === 'dislike') next.dislikes_count++
    Object.assign(post, next)
    patchLocal(post.id, next)
    try {
      const res = cancel ? await PostsApi.unvote(post.id) : await PostsApi.vote(post.id, value)
      Object.assign(post, res.data)
      patchLocal(post.id, res.data)
      // the team's «кто голосовал» list of this post is out of date now, and so are the card's faces
      useVotersStore().forget(post.id)
      if (team.value) void refresh(true)
    } catch (e) {
      Object.assign(post, was)
      patchLocal(post.id, was)
      notification.error(errorMessage(e))
    }
  }

  async function updatePost(id: number, patch: PostPatch) {
    const res = await PostsApi.update(id, patch)
    patchLocal(id, res.data)
    return res.data
  }

  async function deletePost(id: number) {
    await PostsApi.remove(id)
    posts.value = posts.value.filter((p) => p.id !== id)
    topPosts.value = topPosts.value.filter((p) => p.id !== id)
  }

  // ---------- Массовый выбор карточек в списке ----------
  // Режим выбора включается кнопкой «Выделить»: только тогда у карточек появляются
  // чекбоксы. Shift-клик расширяет диапазон от «якоря» (прошлый клик) до текущего
  // по видимому порядку. Bulk-операции чистят выбор, но режим остаётся — удобно гонять
  // пачки подряд. Смена секции/фильтров выключает режим целиком.

  const selectionMode = ref(false)
  // ref<Set> с immutable-reassign вместо reactive(Set): трекинг `has(id)` на reactive-Set
  // в Vue 3 в нашем кейсе не триггерил ре-рендер `<input :checked>` — пересоздание ссылки надёжнее.
  const selectedIds = ref<Set<number>>(new Set())
  const lastSelectedId = ref<number | null>(null)

  function mutate(fn: (next: Set<number>) => void) {
    const next = new Set(selectedIds.value)
    fn(next)
    selectedIds.value = next
  }

  function exitSelection() {
    selectionMode.value = false
    selectedIds.value = new Set()
    lastSelectedId.value = null
  }

  function toggleSelectionMode() {
    if (selectionMode.value) exitSelection()
    else selectionMode.value = true
  }

  const selectionCount = computed(() => selectedIds.value.size)
  const hasSelection = computed(() => selectedIds.value.size > 0)
  function isSelected(id: number): boolean {
    return selectedIds.value.has(id)
  }

  function toggleSelect(id: number) {
    mutate((next) => {
      if (next.has(id)) next.delete(id)
      else next.add(id)
    })
    lastSelectedId.value = id
  }

  function selectRange(toId: number) {
    const anchor = lastSelectedId.value
    const order = posts.value.map((p) => p.id)
    const from = anchor === null ? -1 : order.indexOf(anchor)
    const to = order.indexOf(toId)
    mutate((next) => {
      if (from < 0 || to < 0 || from === to) {
        next.add(toId)
        return
      }
      const [a, b] = from < to ? [from, to] : [to, from]
      for (const id of order.slice(a, b + 1)) next.add(id)
    })
    lastSelectedId.value = toId
  }

  function clearSelection() {
    selectedIds.value = new Set()
    lastSelectedId.value = null
  }

  /** Выбранные посты, которые ещё лежат в текущем списке — для подсказок в UI (кто получит ответ). */
  const selectedPosts = computed(() => posts.value.filter((p) => selectedIds.value.has(p.id)))

  /** Все ли выбранные скрыты (чтобы кнопка «скрыть» превратилась в «показать»). */
  const allSelectedHidden = computed(() => selectedPosts.value.length > 0 && selectedPosts.value.every((p) => p.is_hidden))

  function patchLocalMany(updated: Post[]) {
    for (const next of updated) patchLocal(next.id, next)
  }

  /**
   * Откатить bulk-операцию: по снапшоту каждой карточки шлём one-shot patch через single-post эндпоинт.
   * Параллельно; локальный стейт патчится из ответов. Исходный bulk-эндпоинт берёт один payload на всех —
   * для разнородного восстановления (у каждого свой прежний столбец/категория) one-shot честнее.
   */
  async function restoreMany(snaps: Array<{ id: number; patch: PostPatch }>) {
    const results = await Promise.all(snaps.map(async (s) => (await PostsApi.update(s.id, s.patch)).data))
    patchLocalMany(results)
  }

  async function bulkMove(statusId: number, teamComment: string | null = null) {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    const snaps = selectedPosts.value.map((p) => ({
      id: p.id,
      patch: { status_id: p.status.id, team_comment: p.team_comment } as PostPatch,
    }))
    const res = await PostsApi.bulkMove(ids, statusId, teamComment)
    patchLocalMany(res.data)
    const target = statuses.value.find((s) => s.id === statusId)
    exitSelection()
    notification.success(`Перенесено (${ids.length}) в «${target?.name ?? '…'}»`, {
      label: 'Отменить',
      run: () => restoreMany(snaps).catch((e) => notification.error(errorMessage(e))),
    })
  }

  async function bulkCategorize(categoryId: number | null) {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    const snaps = selectedPosts.value.map((p) => ({
      id: p.id,
      patch: { category_id: p.category?.id ?? null } as PostPatch,
    }))
    const res = await PostsApi.bulkCategorize(ids, categoryId)
    patchLocalMany(res.data)
    const target = categories.value.find((c) => c.id === categoryId)
    exitSelection()
    notification.success(
      categoryId === null
        ? `Снята категория у ${ids.length} карточек`
        : `Назначена «${target?.name ?? '…'}» (${ids.length})`,
      {
        label: 'Отменить',
        run: () => restoreMany(snaps).catch((e) => notification.error(errorMessage(e))),
      },
    )
  }

  async function bulkHide(hidden: boolean) {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    const snaps = selectedPosts.value.map((p) => ({
      id: p.id,
      patch: { is_hidden: p.is_hidden } as PostPatch,
    }))
    const res = await PostsApi.bulkHide(ids, hidden)
    patchLocalMany(res.data)
    // с выключенным «показать скрытые» карточки должны уехать с доски прямо сейчас
    if (hidden && !view.showHidden) await refresh(true)
    exitSelection()
    notification.success(hidden ? `Скрыто с доски (${ids.length})` : `Снова на доске (${ids.length})`, {
      label: 'Отменить',
      run: async () => {
        try {
          await restoreMany(snaps)
          if (hidden && !view.showHidden) await refresh(true)
        } catch (e) {
          notification.error(errorMessage(e))
        }
      },
    })
  }

  async function bulkDelete() {
    const ids = Array.from(selectedIds.value)
    if (!ids.length) return
    await PostsApi.bulkDelete(ids)
    posts.value = posts.value.filter((p) => !selectedIds.value.has(p.id))
    topPosts.value = topPosts.value.filter((p) => !selectedIds.value.has(p.id))
    exitSelection()
    notification.success(`Удалено карточек: ${ids.length}`)
  }

  // Жёсткий выход — контекст изменился: ушли из list в top, сменили раскладку, или перещёлкнули
  // «показывать скрытые» (у которых другой список). Выбор привязан к контексту, в новом он бесполезен.
  watch(
    () => [view.section, view.layout, view.showHidden],
    () => exitSelection(),
  )
  // Мягкий сброс — просто сузили срез внутри той же сессии (поиск, фильтры): выбор чистим,
  // но из режима не выгоняем. Админ сузила список, продолжает триаж в нём.
  watch(
    () => [view.category, view.status, view.search, view.priority],
    () => {
      if (selectionMode.value) clearSelection()
    },
  )

  async function addCategory(name: string, color: PaletteColor | null) {
    const res = await CategoriesApi.create(currentSlug(), name, color)
    project.value?.categories.push(res.data)
  }

  async function updateCategory(id: number, name: string, color: PaletteColor | null) {
    const res = await CategoriesApi.update(id, name, color)
    const category = project.value?.categories.find((c) => c.id === id)
    if (category) Object.assign(category, res.data)
    for (const post of [...posts.value, ...topPosts.value]) if (post.category?.id === id) post.category = { ...res.data }
  }

  /** Posts of a deleted category stay on the board without a category. */
  async function deleteCategory(id: number) {
    await CategoriesApi.remove(id)
    if (project.value) project.value.categories = project.value.categories.filter((c) => c.id !== id)
    if (view.category === id) view.category = ''
    for (const post of [...posts.value, ...topPosts.value]) if (post.category?.id === id) post.category = null
  }

  async function addStatus(name: string, color: PaletteColor | null) {
    const res = await StatusesApi.create(currentSlug(), name, color)
    project.value?.statuses.push(res.data)
    project.value?.statuses.sort((a, b) => a.position - b.position || a.id - b.id)
  }

  async function updateStatus(id: number, name: string, color: PaletteColor | null) {
    const res = await StatusesApi.update(id, name, color)
    const status = project.value?.statuses.find((s) => s.id === id)
    if (status) Object.assign(status, res.data)
    // в карточках статус хранится объектом — обновим имя и цвет там тоже
    for (const post of [...posts.value, ...topPosts.value]) {
      if (post.status.id === id) Object.assign(post.status, res.data)
    }
  }

  /** Переупорядочивание кастомных столбцов (drag&drop); системные остаются на своих позициях. */
  async function reorderStatuses(ids: number[]) {
    const res = await StatusesApi.reorder(currentSlug(), ids)
    if (project.value) project.value.statuses = res.data.sort((a, b) => a.position - b.position || a.id - b.id)
  }

  /** Удаление кастомного столбца: сервер переносит посты в «Новое», локально делаем то же. */
  async function deleteStatus(id: number) {
    await StatusesApi.remove(id)
    const fallback = defaultStatus.value
    if (project.value) project.value.statuses = project.value.statuses.filter((s) => s.id !== id)
    if (view.status === id) view.status = ''
    if (fallback) {
      for (const post of [...posts.value, ...topPosts.value]) {
        if (post.status.id === id) post.status = { ...fallback }
      }
    }
  }

  async function logout() {
    // through the cabinet store, so /app/* doesn't keep the session it cached
    await auth.logout()
    me.value = null
    if (view.sort === 'priority') view.sort = 'top'
    // on a private board's gate the outsider becomes a guest: same gate, with name and email fields
    if (noAccess.value) {
      noAccess.value = false
      privateLocked.value = true
    }
  }

  return {
    project, notFound, unpublished, privateLocked, noAccess, gateName, me, role, team, configure, readOnly, categories, statuses, defaultStatus, view, filtered, resetFilters, posts, page, lastPage, postsTotal, newCount, loading, loadingMore, postsLoaded, loadError, ranking, leaderboard, weeklyWinners, weeklyWinnersMeta,
    open, refresh, refreshMe, loadPosts, loadWeeklyWinners, vote, updatePost, deletePost,
    addCategory, updateCategory, deleteCategory,
    addStatus, updateStatus, deleteStatus, reorderStatuses,
    logout,
    startPolling, stopPolling,
    selectionMode, selectedIds, selectionCount, hasSelection, isSelected, selectedPosts, allSelectedHidden,
    exitSelection, toggleSelectionMode,
    toggleSelect, selectRange, clearSelection,
    bulkMove, bulkCategorize, bulkHide, bulkDelete,
  }
})
