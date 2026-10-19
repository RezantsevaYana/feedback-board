import { computed, reactive, ref } from 'vue'
import { defineStore } from 'pinia'
import { api, ApiError } from '@/api/client'
import type { Category, Item, Me, Paginated, Post, PostPriority, PostStatus, Project, VoteState, VoteValue } from '@/api/types'
import { useToast } from '@/lib/toast'

export type Section = 'posts' | 'top'
export type Layout = 'kanban' | 'list'
export type Sort = 'top' | 'new' | 'priority'
type Editable = Partial<Pick<Post, 'title' | 'description' | 'status' | 'priority' | 'team_comment' | 'is_hidden'>> & { category_id?: number | null }

/** Columns show everything at once (MVP volumes); the list pages by 20 (TZ 5.2). */
const KANBAN_LIMIT = 100
const LIST_PAGE = 20

/** Popularity = likes minus dislikes. */
export const score = (p: Post) => p.likes_count - p.dislikes_count

export const useBoardStore = defineStore('board', () => {
  const toast = useToast()
  const errorText = (e: unknown) => (e instanceof ApiError ? e.message : String(e))

  const project = ref<Project | null>(null)
  const notFound = ref(false)
  const me = ref<Me | null>(null)
  /** Team mode: logged in and managing this project (the API checks it again on every call). */
  const team = computed(() => !!me.value && !!project.value && me.value.projects.some((p) => p.slug === project.value?.slug))
  const categories = computed<Category[]>(() => project.value?.categories ?? [])

  const view = reactive({
    section: 'posts' as Section,
    layout: 'kanban' as Layout,
    category: '' as '' | number | 'none',
    status: '' as '' | PostStatus,
    sort: 'top' as Sort,
    topScope: 'open' as 'open' | 'all',
    // team only
    priority: '' as '' | PostPriority | 'none',
    showHidden: true,
  })

  const posts = ref<Post[]>([])
  const page = ref(1)
  const lastPage = ref(1)
  const loading = ref(false)
  const loadError = ref('')
  const topPosts = ref<Post[]>([])

  async function open(slug: string) {
    if (project.value?.slug === slug) return
    notFound.value = false
    project.value = null
    try {
      const [p, user] = await Promise.all([
        api<Item<Project>>(`/public/projects/${encodeURIComponent(slug)}`),
        api<Item<Me>>('/admin/me').then((r) => r.data).catch(() => null),
      ])
      project.value = p.data
      me.value = user
      await refresh()
    } catch (e) {
      if (e instanceof ApiError && e.status === 404) notFound.value = true
      else loadError.value = errorText(e)
    }
  }

  const slugPath = () => encodeURIComponent(project.value?.slug ?? '')

  async function loadPosts(append = false) {
    if (!project.value) return
    const nextPage = append ? page.value + 1 : 1
    loading.value = true
    loadError.value = ''
    try {
      const res = await api<Paginated<Post>>(team.value ? `/admin/projects/${slugPath()}/posts` : `/public/projects/${slugPath()}/posts`, {
        query: {
          category: view.category,
          status: view.layout === 'list' ? view.status : '',
          sort: !team.value && view.sort === 'priority' ? 'top' : view.sort,
          page: nextPage,
          per_page: view.layout === 'kanban' ? KANBAN_LIMIT : LIST_PAGE,
          ...(team.value ? { priority: view.priority, hidden: view.showHidden ? 'include' : 'exclude' } : {}),
        },
      })
      posts.value = append ? [...posts.value, ...res.data] : res.data
      page.value = res.meta.current_page
      lastPage.value = res.meta.last_page
    } catch (e) {
      loadError.value = errorText(e)
    } finally {
      loading.value = false
    }
  }

  async function loadTop() {
    if (!project.value) return
    loading.value = true
    loadError.value = ''
    try {
      const res = await api<Paginated<Post>>(`/public/projects/${slugPath()}/posts`, {
        query: { category: view.category, sort: 'top', per_page: KANBAN_LIMIT },
      })
      topPosts.value = res.data
    } catch (e) {
      loadError.value = errorText(e)
    } finally {
      loading.value = false
    }
  }

  /** Ranking by likes minus dislikes; equal score shares a place, the earlier post goes first. Rejected ones do not compete. */
  const ranking = computed(() => {
    const list = topPosts.value
      .filter((p) => p.status !== 'rejected' && (view.topScope === 'all' || p.status !== 'done'))
      .sort((a, b) => score(b) - score(a) || a.created_at.localeCompare(b.created_at))
    let place = 0
    return list.map((post, i) => {
      const prev = list[i - 1]
      if (!prev || score(post) !== score(prev)) place = i + 1
      return { post, place }
    })
  })

  function refresh() {
    return view.section === 'top' ? loadTop() : loadPosts()
  }

  /** The same post can sit in the board list, the ranking and an open card: keep every copy in sync. */
  function patchLocal(id: number, patch: Partial<Post>) {
    for (const list of [posts.value, topPosts.value]) {
      const post = list.find((p) => p.id === id)
      if (post) Object.assign(post, patch)
    }
  }

  /** Click on 👍 / 💩: a second click on the same one cancels, the other one switches. */
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
      const res = await api<Item<VoteState>>(`/public/posts/${post.id}/vote`, cancel ? { method: 'DELETE' } : { method: 'POST', body: { value } })
      Object.assign(post, res.data)
      patchLocal(post.id, res.data)
    } catch (e) {
      Object.assign(post, was)
      patchLocal(post.id, was)
      toast.error(errorText(e))
    }
  }

  async function updatePost(id: number, patch: Editable) {
    const res = await api<Item<Post>>(`/admin/posts/${id}`, { method: 'PATCH', body: patch })
    patchLocal(id, res.data)
    return res.data
  }

  async function deletePost(id: number) {
    await api(`/admin/posts/${id}`, { method: 'DELETE' })
    posts.value = posts.value.filter((p) => p.id !== id)
    topPosts.value = topPosts.value.filter((p) => p.id !== id)
  }

  async function addCategory(name: string) {
    const res = await api<Item<Category>>(`/admin/projects/${slugPath()}/categories`, { method: 'POST', body: { name } })
    project.value?.categories.push(res.data)
  }

  async function renameCategory(id: number, name: string) {
    const res = await api<Item<Category>>(`/admin/categories/${id}`, { method: 'PATCH', body: { name } })
    const category = project.value?.categories.find((c) => c.id === id)
    if (category) category.name = res.data.name
    for (const post of [...posts.value, ...topPosts.value]) if (post.category?.id === id) post.category = { ...res.data }
  }

  /** Posts of a deleted category stay on the board without a category. */
  async function deleteCategory(id: number) {
    await api(`/admin/categories/${id}`, { method: 'DELETE' })
    if (project.value) project.value.categories = project.value.categories.filter((c) => c.id !== id)
    if (view.category === id) view.category = ''
    for (const post of [...posts.value, ...topPosts.value]) if (post.category?.id === id) post.category = null
  }

  async function login(email: string, password: string) {
    const res = await api<Item<Me>>('/admin/login', { method: 'POST', body: { email, password } })
    // the board reloads itself when team mode switches (watcher in BoardView)
    me.value = res.data
  }

  async function logout() {
    await api('/admin/logout', { method: 'POST' }).catch(() => undefined)
    me.value = null
    if (view.sort === 'priority') view.sort = 'top'
  }

  return {
    project, notFound, me, team, categories, view, posts, page, lastPage, loading, loadError, ranking,
    open, refresh, loadPosts, vote, updatePost, deletePost, addCategory, renameCategory, deleteCategory, login, logout,
  }
})