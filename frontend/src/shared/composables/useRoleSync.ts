import { onBeforeUnmount, onMounted } from 'vue'
import { onForbidden } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useBoardStore } from '@/store/board/useBoardStore'

// /me is read once per tab, so a role changed in another window (an owner demoted me, I'm testing roles) left
// the old buttons in place until a reload, and their requests ended in 403. Read /me again after such a 403
// and when the tab comes back into view: the cabinet's menu and the board's team mode follow the real role.
// Mounted once, in App.vue.
export function useRoleSync() {
  const auth = useAuthStore()
  const board = useBoardStore()

  function sync() {
    if (auth.isAuth) void auth.fetchMe(true).catch(() => undefined)
    void board.refreshMe()
  }
  function onVisible() {
    if (document.visibilityState === 'visible') sync()
  }

  let stop: (() => void) | null = null
  onMounted(() => {
    stop = onForbidden(sync)
    document.addEventListener('visibilitychange', onVisible)
  })
  onBeforeUnmount(() => {
    stop?.()
    document.removeEventListener('visibilitychange', onVisible)
  })
}