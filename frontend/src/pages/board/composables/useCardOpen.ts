import type { Post } from '@/api/types'

/**
 * Проверка «клик на пустое место карточки, а не на интерактивную кнопку/ссылку».
 * Карточка целиком — область открытия, но клик по голосам, копировалке
 * ссылки или любой другой кнопке/ссылке внутри НЕ должен открывать модалку.
 */
export function useCardOpen(emit: (name: 'open', post: Post) => void) {
  function open(post: Post) {
    emit('open', post)
  }
  function onCardClick(e: MouseEvent, post: Post) {
    const target = e.target as HTMLElement | null
    if (target?.closest('button, a, input, label')) return
    open(post)
  }
  function onCardEnter(post: Post) {
    open(post)
  }
  return { onCardClick, onCardEnter }
}
