import { computed, reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import { AuthApi } from '@/api/auth'
import { CategoriesApi } from '@/api/categories'
import { ApiError, errorMessage } from '@/api/client'
import { LeaderboardApi, type LeaderboardEntry, type LeaderboardMetric, type LeaderboardScope } from '@/api/leaderboard'
import { type PostPatch, PostsApi } from '@/api/posts'
import { ProjectsApi } from '@/api/projects'
import { StatusesApi } from '@/api/statuses'
import type { Category, PaletteColor, Me, Post, PostPriority, Project, Status, VoteState, VoteValue } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'

export type Section = 'posts' | 'top' | 'authors'
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
  const me = ref<Me | null>(null)
  /** Team mode: logged in and managing this project (the API checks it again on every call). */
  const team = computed(() => !!me.value && !!project.value && me.value.projects.some((p) => p.slug === project.value?.slug))
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
  const loading = ref(false)
  /** The first page of posts has arrived: until then the board shows skeletons, not empty columns. */
  const postsLoaded = ref(false)
  /** «Показать ещё» is loading the next page (its button spins; no bar over the whole board). */
  const loadingMore = ref(false)
  const loadError = ref('')
  const topPosts = ref<Post[]>([])
  const leaderboard = ref<LeaderboardEntry[]>([])

  async function open(slug: string) {
    if (project.value?.slug === slug) return
    notFound.value = false
    loadError.value = ''
    project.value = null
    postsLoaded.value = false
    try {
      const [p, user] = await Promise.all([
        ProjectsApi.get(slug),
        AuthApi.me().then((r) => r.data).catch(() => null),
      ])
      project.value = p.data
      me.value = user
      await refresh()
    } catch (e) {
      if (e instanceof ApiError && e.status === 404) notFound.value = true
      else loadError.value = errorMessage(e)
    }
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

  async function login(email: string, password: string) {
    const res = await AuthApi.login(email, password)
    // the board reloads itself when team mode switches (watcher in BoardView)
    me.value = res.data
  }

  async function logout() {
    await AuthApi.logout().catch(() => undefined)
    me.value = null
    if (view.sort === 'priority') view.sort = 'top'
  }

  return {
    project, notFound, me, team, categories, statuses, defaultStatus, view, filtered, resetFilters, posts, page, lastPage, loading, loadingMore, postsLoaded, loadError, ranking, leaderboard,
    open, refresh, loadPosts, vote, updatePost, deletePost,
    addCategory, updateCategory, deleteCategory,
    addStatus, updateStatus, deleteStatus,
    login, logout,
    startPolling, stopPolling,
  }
})
