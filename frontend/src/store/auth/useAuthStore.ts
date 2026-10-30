import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { AuthApi } from '@/api/auth'
import { type Board, BoardsApi, type TrashedBoard } from '@/api/boards'
import { ApiError } from '@/api/client'
import { type AcceptResult, type IncomingInvitation, MyInvitationsApi } from '@/api/participants'
import type { BoardSummary, Me } from '@/api/types'

/**
 * Global auth state for the admin app (/app/*): who's logged in, which boards they're on, who invites them.
 * Two sources for boards:
 *  - `me.boards` — short summaries from /me, powers the board switcher.
 *  - `boards`   — full rows with queue_count / last_post_at from /admin/boards, drives the hub;
 *                 `trashed` comes with the same request and fills the hub's trash block.
 *
 * Separate from useBoardStore — the latter is tied to a specific project context and keeps its
 * own `me` for team-mode checks on the public board. Both stores coexist by design.
 */
export const useAuthStore = defineStore('auth', () => {
  const me = ref<Me | null>(null)
  const loaded = ref(false)
  const loading = ref(false)

  const boards = ref<Board[]>([])
  const trashed = ref<TrashedBoard[]>([])
  const boardsLoaded = ref(false)
  const boardsLoading = ref(false)
  /** «Вас приглашают» on the hub: invitations to my email, accepted or declined right there. */
  const incoming = ref<IncomingInvitation[]>([])

  const isAuth = computed(() => !!me.value)
  /** Short summaries kept in sync with the server copy — used by the board switcher. */
  const boardSummaries = computed<BoardSummary[]>(() => me.value?.boards ?? [])

  /**
   * Called by the router guard before entering /app/*; a 401 is a legitimate "guest" state.
   * `force` skips the cache: after the letter's link, so the banner goes away.
   */
  async function fetchMe(force = false): Promise<void> {
    if (loading.value || (loaded.value && !force)) return
    loading.value = true
    try {
      const res = await AuthApi.me()
      me.value = res.data
    } catch (e) {
      if (!(e instanceof ApiError) || e.status !== 401) throw e
      me.value = null
    } finally {
      loaded.value = true
      loading.value = false
    }
  }

  async function fetchBoards(): Promise<void> {
    if (boardsLoading.value) return
    boardsLoading.value = true
    try {
      const res = await BoardsApi.list()
      boards.value = res.data
      trashed.value = res.trashed
      boardsLoaded.value = true
    } finally {
      boardsLoading.value = false
    }
  }

  async function fetchIncoming(): Promise<void> {
    const res = await MyInvitationsApi.list()
    incoming.value = res.data
  }

  /** Join from the hub: the board appears in «Я участник» / «Я веду», /me learns the new role for the board itself. */
  async function acceptIncoming(id: number): Promise<AcceptResult> {
    const res = await MyInvitationsApi.accept(id)
    incoming.value = incoming.value.filter((i) => i.id !== id)
    await Promise.all([fetchBoards(), fetchMe(true)])
    return res.data
  }

  async function declineIncoming(id: number): Promise<void> {
    await MyInvitationsApi.decline(id)
    incoming.value = incoming.value.filter((i) => i.id !== id)
  }

  /** Merge a freshly created board into both the full list and the /me summaries. */
  function addBoard(board: Board): void {
    boards.value = [board, ...boards.value]
    if (me.value) {
      const brief = { id: board.id, name: board.name, slug: board.slug, role: board.role }
      me.value.boards = [brief, ...me.value.boards]
      me.value.projects = [brief, ...me.value.projects]
    }
  }

  /** After a rename in settings: the hub card and the /me summaries show the new name without a refetch. */
  function renameBoard(slug: string, name: string): void {
    for (const b of [...boards.value, ...(me.value?.boards ?? []), ...(me.value?.projects ?? [])]) {
      if (b.slug === slug) b.name = name
    }
  }

  /** Boards where access requests wait for my decision — «Ждут решения на ваших досках» in the hub. */
  const awaitingDecision = computed(() => (me.value?.projects ?? []).filter((b) => (b.pending_requests ?? 0) > 0))

  /** The participants page knows the fresh count; the hub block and the «Участники» badge follow it. */
  function setPendingRequests(slug: string, count: number): void {
    for (const b of [...(me.value?.boards ?? []), ...(me.value?.projects ?? [])]) {
      if (b.slug === slug) b.pending_requests = count
    }
  }

  /** Delete to the trash; the board disappears from the hub, the switcher and team mode at once. */
  async function trashBoard(slug: string, confirm: string): Promise<TrashedBoard> {
    const res = await BoardsApi.destroy(slug, confirm)
    boards.value = boards.value.filter((b) => b.slug !== slug)
    trashed.value = [res.data, ...trashed.value.filter((b) => b.slug !== slug)]
    if (me.value) {
      me.value.boards = me.value.boards.filter((b) => b.slug !== slug)
      me.value.projects = me.value.projects.filter((b) => b.slug !== slug)
    }
    return res.data
  }

  /** Back from the trash, to the top of the hub (it was the most recent thing touched). */
  async function restoreBoard(slug: string): Promise<Board> {
    const res = await BoardsApi.restore(slug)
    trashed.value = trashed.value.filter((b) => b.slug !== slug)
    boards.value = [res.data, ...boards.value.filter((b) => b.slug !== slug)]
    if (me.value) {
      // the role matters: team mode on the board looks it up in me.projects
      const brief = { id: res.data.id, name: res.data.name, slug: res.data.slug, role: res.data.role }
      const bySlug = (list: typeof me.value.boards) => [...list.filter((b) => b.slug !== slug), brief].sort((a, b) => a.name.localeCompare(b.name))
      me.value.boards = bySlug(me.value.boards)
      me.value.projects = bySlug(me.value.projects)
    }
    return res.data
  }

  async function register(name: string, email: string, password: string): Promise<void> {
    const res = await AuthApi.register(name, email, password)
    me.value = res.data
    loaded.value = true
  }

  async function login(email: string, password: string): Promise<void> {
    const res = await AuthApi.login(email, password)
    me.value = res.data
    loaded.value = true
  }

  /** The link from the letter; then /me again, so this tab knows the address is confirmed (and who is logged in). */
  async function verifyEmail(id: string, hash: string, expires: string, signature: string): Promise<void> {
    await AuthApi.verifyEmail(id, hash, expires, signature)
    // the address is confirmed either way; a failed /me only leaves the banner until the next reload
    await fetchMe(true).catch(() => undefined)
  }

  async function forgotPassword(email: string): Promise<void> {
    await AuthApi.forgotPassword(email)
  }

  /** The server logs in with the new password, so the page goes straight to the hub. */
  async function resetPassword(token: string, email: string, password: string): Promise<void> {
    const res = await AuthApi.resetPassword(token, email, password)
    me.value = res.data
    loaded.value = true
  }

  /** «Профиль» in the account settings; the header menu shows the new name at once. */
  async function updateName(name: string): Promise<void> {
    const res = await AuthApi.updateProfile(name)
    me.value = res.data
  }

  /** From the account settings; the session stays, so nothing else in the store changes. */
  async function changePassword(currentPassword: string, password: string): Promise<void> {
    await AuthApi.changePassword(currentPassword, password)
  }

  async function resendVerification(): Promise<void> {
    await AuthApi.resendVerification()
  }

  async function logout(): Promise<void> {
    await AuthApi.logout().catch(() => undefined)
    me.value = null
    loaded.value = true
    // next admin session starts clean
    boards.value = []
    trashed.value = []
    incoming.value = []
    boardsLoaded.value = false
  }

  return {
    me, loaded, loading, isAuth, boardSummaries, awaitingDecision, setPendingRequests,
    boards, trashed, boardsLoaded, boardsLoading, incoming,
    fetchMe, fetchBoards, fetchIncoming, acceptIncoming, declineIncoming, addBoard, renameBoard, trashBoard, restoreBoard,
    register, login, logout, verifyEmail, resendVerification, forgotPassword, resetPassword, changePassword, updateName,
  }
})
