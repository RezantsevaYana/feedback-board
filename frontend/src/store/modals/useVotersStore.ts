import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { VoteValue } from '@/api/types'

// «Кто голосовал» — модалка, которую открывает VoteButtons.
// Хранит только id поста и начальный таб (like / dislike), сам список подтянет VotersModal.
export const useVotersStore = defineStore('voters', () => {
  const postId = ref<number | null>(null)
  const initialKind = ref<VoteValue>('like')

  function open(id: number, kind: VoteValue) {
    postId.value = id
    initialKind.value = kind
  }

  function close() {
    postId.value = null
  }

  return { postId, initialKind, open, close }
})
