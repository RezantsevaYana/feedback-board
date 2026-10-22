import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { AuthApi } from '@/api/auth'
import { type Board, BoardsApi } from '@/api/boards'
import { ApiError } from '@/api/client'
import type { BoardSummary, Me } from '@/api/types'

/**
 * Global auth state for the admin app (/app/*): who's logged in and which boards they own.
 * Two sources for boards:
 *  - `me.boards` — short summaries from /me, powers the board switcher (iteration 4).
 *  - `boards`   — full rows with queue_count / last_post_at from /admin/boards, drives the hub.
 *
 * Separate from useBoardStore — the latter is tied to a specific project context and keeps its
 * own `me` for team-mode checks on the public board. Both stores coexist by design.
 */
export const useAuthStore = defineStore('auth', () => {
  const me = ref<Me | null>(null)
  const loaded = ref(false)
  const loading = ref(false)

  const boards = ref<Board[]>([])
  const boardsLoaded = ref(false)
  const boardsLoading = ref(false)

  const isAuth = computed(() => !!me.value)
  /** Short summaries kept in sync with the server copy — used by the board switcher. */
  const boardSummaries = computed<BoardSummary[]>(() => me.value?.boards ?? [])

  /** Called by the router guard before entering /app/*; a 401 is a legitimate "guest" state. */
  async function fetchMe(): Promise<void> {
    if (loaded.value || loading.value) return
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
      boardsLoaded.value = true
    } finally {
      boardsLoading.value = false
    }
  }

  /** Merge a freshly created board into both the full list and the /me summaries. */
  function addBoard(board: Board): void {
    boards.value = [board, ...boards.value]
    if (me.value) {
      me.value.boards = [{ id: board.id, name: board.name, slug: board.slug }, ...me.value.boards]
    }
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

  async function logout(): Promise<void> {
    await AuthApi.logout().catch(() => undefined)
    me.value = null
    loaded.value = true
    // next admin session starts clean
    boards.value = []
    boardsLoaded.value = false
  }

  return {
    me, loaded, loading, isAuth, boardSummaries,
    boards, boardsLoaded, boardsLoading,
    fetchMe, fetchBoards, addBoard,
    register, login, logout,
  }
})
