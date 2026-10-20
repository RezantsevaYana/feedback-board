import { ref } from 'vue'

export type Theme = 'system' | 'light' | 'dark'

const KEY = 'fb:theme'

function readStored(): Theme {
  try {
    const value = localStorage.getItem(KEY)
    return value === 'light' || value === 'dark' ? value : 'system'
  } catch {
    return 'system'
  }
}

function apply(t: Theme) {
  const el = document.documentElement
  if (t === 'system') el.removeAttribute('data-theme')
  else el.setAttribute('data-theme', t)
}

// Один общий ref на всё приложение. Применяется при импорте — чтобы верстка
// на первом paint уже была в выбранной теме.
const theme = ref<Theme>(readStored())
apply(theme.value)

/** Три позиции: system → light → dark → system → … */
export function useTheme() {
  function setTheme(t: Theme) {
    theme.value = t
    apply(t)
    try {
      localStorage.setItem(KEY, t)
    } catch {
      /* приват-окно, квота — молча */
    }
  }
  function cycle() {
    const next: Theme = theme.value === 'system' ? 'light' : theme.value === 'light' ? 'dark' : 'system'
    setTheme(next)
  }
  return { theme, setTheme, cycle }
}
