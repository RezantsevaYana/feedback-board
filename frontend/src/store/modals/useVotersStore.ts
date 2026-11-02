import { defineStore } from 'pinia'
import { ref } from 'vue'
import { PostsApi } from '@/api/posts'
import type { Voter, VoteValue } from '@/api/types'

// «Кто голосовал» (team only): which post's list is open (VotersModal — a bottom sheet on a phone) and the lists
// already fetched, so the hover popover in VoteButtons and the sheet don't ask again while the board is open.
// A vote on the post drops its list (useBoardStore.vote).
export const useVotersStore = defineStore('voters', () => {
  const postId = ref<number | null>(null)
  const postTitle = ref('')
  const initialKind = ref<VoteValue>('like')
  const cache = new Map<number, Promise<Voter[]>>()

  function open(id: number, kind: VoteValue, title = '') {
    postId.value = id
    postTitle.value = title
    initialKind.value = kind
  }

  function close() {
    postId.value = null
  }

  // the list of a post, once per board visit; a failed load isn't kept, so the next hover tries again
  function load(id: number): Promise<Voter[]> {
    let list = cache.get(id)
    if (!list) {
      list = PostsApi.voters(id).then((r) => r.data)
      list.catch(() => cache.delete(id))
      cache.set(id, list)
    }
    return list
  }

  function forget(id: number) {
    cache.delete(id)
  }

  return { postId, postTitle, initialKind, open, close, load, forget }
})
