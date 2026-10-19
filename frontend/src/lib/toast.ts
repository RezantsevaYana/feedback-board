import { reactive } from 'vue'

const state = reactive({ text: '', error: false })
let timer: ReturnType<typeof setTimeout> | undefined

function show(text: string, error = false) {
  state.text = text
  state.error = error
  clearTimeout(timer)
  timer = setTimeout(() => (state.text = ''), error ? 4000 : 2200)
}

/** One short message at the bottom of the screen. */
export function useToast() {
  return { state, show, error: (text: string) => show(text, true) }
}