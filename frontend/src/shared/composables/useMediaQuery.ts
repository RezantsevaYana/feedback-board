import { onBeforeUnmount, ref } from 'vue'

// Reactive window.matchMedia: true while the query matches, follows resize / rotation.
// Use it when markup (not just styles) differs by breakpoint; plain layout stays in SCSS.
export function useMediaQuery(query: string) {
  const list = typeof window !== 'undefined' ? window.matchMedia(query) : null
  const matches = ref(list?.matches ?? false)
  const update = (event: MediaQueryListEvent) => {
    matches.value = event.matches
  }
  list?.addEventListener('change', update)
  onBeforeUnmount(() => list?.removeEventListener('change', update))
  return matches
}