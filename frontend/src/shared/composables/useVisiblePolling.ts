import { onBeforeUnmount, onMounted } from 'vue'

/** Refresh visible screens without overlapping requests or keeping background tabs busy. */
export function useVisiblePolling(refresh: () => Promise<unknown>, interval = 10000) {
  let timer: ReturnType<typeof setInterval> | undefined
  let busy = false
  let stopped = false

  async function run() {
    if (stopped || busy || document.visibilityState !== 'visible') return
    busy = true
    try { await refresh() } catch { /* Keep the current screen on a transient failure. */ }
    finally { busy = false }
  }
  onMounted(() => {
    timer = setInterval(() => void run(), interval)
    document.addEventListener('visibilitychange', run)
    window.addEventListener('focus', run)
  })
  onBeforeUnmount(() => {
    stopped = true
    clearInterval(timer)
    document.removeEventListener('visibilitychange', run)
    window.removeEventListener('focus', run)
  })
}
