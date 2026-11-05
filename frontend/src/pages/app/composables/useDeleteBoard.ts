import { useRouter } from 'vue-router'
import { BoardsApi } from '@/api/boards'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/store/auth/useAuthStore'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { fmtDate, plural } from '@/shared/utils/format'

// «Удалить доску» at the bottom of the settings page: the shared ConfirmModal, a plain danger confirm, since the board
// only goes to the trash and «Восстановить» brings it back. The API still wants the slug as `confirm`, we pass it.
// After it we go to the hub, where the trash shows the board.
export function useDeleteBoard() {
  const auth = useAuthStore()
  const confirm = useConfirmStore()
  const notification = useNotificationStore()
  const router = useRouter()

  async function deleteBoard(slug: string, name: string) {
    let retention = 'Какое-то время'
    try {
      const n = (await BoardsApi.show(slug)).data.trash_retention_days
      retention = `${plural(n, 'Первый', 'Первые', 'Первые')} ${n} ${plural(n, 'день', 'дня', 'дней')}`
    } catch {
      // the period is only a hint in the text; the deletion itself doesn't depend on it
    }

    const ok = await confirm.ask({
      title: `Удалить «${name}»?`,
      message: `Доска уйдёт в корзину вместе с предложениями, категориями и статусами, виджет и ссылка перестанут работать. ${retention} её можно восстановить из списка досок, потом она удалится навсегда.`,
      confirmText: 'Удалить доску',
      danger: true,
    })
    if (!ok) return

    try {
      const trashed = await auth.trashBoard(slug, slug)
      notification.success(`«${trashed.name}» в корзине до ${fmtDate(trashed.purge_at)}`, {
        label: 'Восстановить',
        run: () => auth.restoreBoard(slug).then(() => notification.success('Доска восстановлена')).catch((e) => notification.error(errorMessage(e))),
      })
      router.push('/app/boards')
    } catch (e) {
      notification.error(errorMessage(e))
    }
  }

  return { deleteBoard }
}