import { onBeforeUnmount, onMounted } from 'vue'

/** Calls `handler` on Esc while the component is mounted. */
export function useEscapeKey(handler: () => void) {
  function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') handler()
  }
  onMounted(() => document.addEventListener('keydown', onKey))
  onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
}