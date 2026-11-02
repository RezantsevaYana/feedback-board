import { computed, watch, type Ref } from 'vue'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { canConfigure } from '@/shared/utils/roles'

/** Same words as a 403 on load (boardLoadError), for the case when the rights went away while the page was open. */
export const NO_CONFIGURE_TEXT = 'Настройки этой доски доступны только владельцу и админам.'

// «Настройки» and «Доступ» of a board: the role was lowered in another window and /me came back without the rights
// (useRoleSync) — the page drops its forms instead of leaving buttons that end in 403.
export function useLostAccess(slug: Ref<string>, onLost: () => void) {
  const auth = useAuthStore()
  const role = computed(() => auth.me?.projects.find((b) => b.slug === slug.value)?.role)
  watch(role, (next, prev) => {
    if (canConfigure(prev) && !canConfigure(next)) onLost()
  })
}
