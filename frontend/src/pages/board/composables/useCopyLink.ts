import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'

/**
 * Copies the absolute link to a post (route `post`) so it can be sent to a chat.
 * `copied` stays true for a moment to show the check mark.
 */
export function useCopyLink() {
  const router = useRouter()
  const board = useBoardStore()
  const notification = useNotificationStore()
  const copied = ref(false)
  let timer: ReturnType<typeof setTimeout> | null = null

  async function copy(postId: number) {
    const slug = board.project?.slug
    if (!slug) return
    const path = router.resolve({ name: 'post', params: { slug, id: postId } }).href
    const url = `${window.location.origin}${path}`
    try {
      // the clipboard API only exists on https and localhost
      if (!navigator.clipboard) throw new Error('no clipboard')
      await navigator.clipboard.writeText(url)
      copied.value = true
      notification.success('Ссылка скопирована')
      if (timer) clearTimeout(timer)
      timer = setTimeout(() => (copied.value = false), 1600)
    } catch {
      notification.error(`Не получилось скопировать. Ссылка: ${url}`)
    }
  }

  return { copied, copy }
}