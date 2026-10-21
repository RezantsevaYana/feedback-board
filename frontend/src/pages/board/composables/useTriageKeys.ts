import { nextTick, onBeforeUnmount, onMounted } from 'vue'
import type { Post, Status } from '@/api/types'
import { useBoardStore } from '@/store/board/useBoardStore'

/**
 * Team keyboard on the board: J / K step through cards, 1–9 move the focused card to that column,
 * H hides or shows it, Enter opens it (the card's own title button). Keys are matched by `code`,
 * so they work in the Russian layout too. Ignored while typing or with a dialog open.
 */
export function useTriageKeys(actions: { move: (post: Post, status: Status) => unknown; toggleHidden: (post: Post) => unknown }) {
  const board = useBoardStore()

  function cards() {
    return Array.from(document.querySelectorAll<HTMLElement>('[data-post-id] .card-open'))
  }
  function focusedPost(): Post | undefined {
    const el = (document.activeElement as HTMLElement | null)?.closest<HTMLElement>('[data-post-id]')
    const id = Number(el?.dataset.postId)
    return id ? board.posts.find((p) => p.id === id) : undefined
  }
  function step(by: number) {
    const all = cards()
    if (!all.length) return
    const i = all.indexOf(document.activeElement as HTMLElement)
    const next = all[i < 0 ? (by > 0 ? 0 : all.length - 1) : Math.min(all.length - 1, Math.max(0, i + by))]!
    next.focus()
    next.scrollIntoView({ block: 'nearest', inline: 'nearest' })
  }

  function onKey(e: KeyboardEvent) {
    if (!board.team || board.view.section !== 'posts') return
    if (e.metaKey || e.ctrlKey || e.altKey || e.defaultPrevented) return
    const target = e.target as HTMLElement | null
    if (target?.closest('input, textarea, select, [contenteditable], [role="menu"], [role="listbox"]')) return
    if (document.body.classList.contains('locked')) return // a modal is open

    if (e.code === 'KeyJ') step(1)
    else if (e.code === 'KeyK') step(-1)
    else if (e.code === 'KeyH') {
      const post = focusedPost()
      if (!post) return
      actions.toggleHidden(post)
    } else if (/^Digit[1-9]$/.test(e.code)) {
      const post = focusedPost()
      const status = board.statuses[Number(e.code.slice(5)) - 1]
      if (!post || !status) return
      // the card is re-rendered in its new column: give it the focus back so J / K continue from it
      Promise.resolve(actions.move(post, status)).then(() =>
        nextTick(() => document.querySelector<HTMLElement>(`[data-post-id="${post.id}"] .card-open`)?.focus()),
      )
    } else return
    e.preventDefault()
  }

  onMounted(() => document.addEventListener('keydown', onKey))
  onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
}