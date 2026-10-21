import { ref } from 'vue'
import { errorMessage } from '@/api/client'
import type { Post, Status, SystemStatusSlug } from '@/api/types'
import { STATUS_REPLY } from '@/shared/constants/dict'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'

/**
 * Team actions on a card, shared by drag-and-drop, the card menu and the keyboard.
 * «Готово» / «Отклонено» first ask for the reply (StatusReplyModal reads `asking`);
 * every other change applies at once and offers «Отменить» in the toast.
 */
export function useTriage() {
  const board = useBoardStore()
  const notification = useNotificationStore()
  const asking = ref<{ post: Post; status: Status } | null>(null)

  async function undo(id: number, patch: Parameters<typeof board.updatePost>[1]) {
    try {
      await board.updatePost(id, patch)
    } catch (e) {
      notification.error(errorMessage(e))
    }
  }

  async function move(post: Post, status: Status) {
    if (post.status.id === status.id) return
    if (STATUS_REPLY[status.slug as SystemStatusSlug]) {
      asking.value = { post, status }
      return
    }
    const prev = post.status.id
    try {
      await board.updatePost(post.id, { status_id: status.id })
      notification.success(`Перенесено в «${status.name}»`, {
        label: 'Отменить',
        run: () => undo(post.id, { status_id: prev }),
      })
    } catch (e) {
      notification.error(errorMessage(e))
    }
  }

  async function toggleHidden(post: Post) {
    const hidden = !post.is_hidden
    try {
      await board.updatePost(post.id, { is_hidden: hidden })
      // with hidden posts filtered out, the card has to leave the board now
      if (hidden && !board.view.showHidden) board.refresh(true)
      notification.success(hidden ? 'Скрыто с доски' : 'Снова на доске', {
        label: 'Отменить',
        run: async () => {
          await undo(post.id, { is_hidden: !hidden })
          if (!board.view.showHidden) board.refresh(true)
        },
      })
    } catch (e) {
      notification.error(errorMessage(e))
    }
  }

  return { asking, move, toggleHidden }
}