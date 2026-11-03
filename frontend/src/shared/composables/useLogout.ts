import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'

/**
 * «Выйти» from the side menu, the same on the board and in the cabinet. Asks for a confirmation first:
 * clicking «Выйти» right next to «Мои доски» or in the account menu is easy to misfire, and the signing-in
 * form takes a moment to type again. Then the login screen, which brings you back to where you were — unless
 * the caller opts out of the redirect (the home page just re-renders in guest mode, no need to send the user
 * to /login from a page they came to as a visitor).
 * The board store's logout also clears the cabinet's cached session (useAuthStore).
 */
export function useLogout(options: { redirect?: boolean } = {}) {
  const route = useRoute()
  const router = useRouter()
  const busy = ref(false)
  const redirect = options.redirect ?? true

  async function logout() {
    if (busy.value) return
    const ok = await useConfirmStore().ask({
      title: 'Выйти из аккаунта?',
      message: 'Чтобы вернуться, нужно будет войти снова по e-mail и паролю.',
      confirmText: 'Выйти',
    })
    if (!ok) return
    busy.value = true
    const next = route.fullPath
    try {
      await useBoardStore().logout()
      if (redirect) await router.push({ name: 'login', query: { next } })
      useNotificationStore().success('Вы вышли из аккаунта')
    } finally {
      busy.value = false
    }
  }

  return { logout, busy }
}
