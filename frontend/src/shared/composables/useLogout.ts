import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'

/**
 * «Выйти» from the side menu, the same on the board and in the cabinet: no confirmation (nothing is lost,
 * signing in again is one form), then the login screen, which brings you back to where you were.
 * The board store's logout also clears the cabinet's cached session (useAuthStore).
 */
export function useLogout() {
  const route = useRoute()
  const router = useRouter()
  const busy = ref(false)

  async function logout() {
    if (busy.value) return
    busy.value = true
    const next = route.fullPath
    try {
      await useBoardStore().logout()
      await router.push({ name: 'login', query: { next } })
      useNotificationStore().success('Вы вышли из аккаунта')
    } finally {
      busy.value = false
    }
  }

  return { logout, busy }
}
