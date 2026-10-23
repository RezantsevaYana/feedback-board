import { nextTick, onBeforeUnmount, onMounted, type Ref } from 'vue'

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

// dialogs can stack (a confirm over the categories modal): only the topmost one handles Tab
const stack: symbol[] = []

function focusables(root: HTMLElement): HTMLElement[] {
  return Array.from(root.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((el) => el.offsetParent !== null || el === document.activeElement)
}

/**
 * Keyboard focus for a dialog while it's mounted: moves in on open, Tab cycles inside, goes back on close.
 * Returns `isTop()` so the dialog's own keys (Esc) also act only on the topmost one.
 * First stop: an element marked `autofocus` (the browser ignores that attribute after page load),
 * else the first field, else the first button, else the dialog itself (needs tabindex="-1").
 */
export function useFocusTrap(root: Ref<HTMLElement | null>) {
  const id = Symbol('focus-trap')
  let returnTo: HTMLElement | null = null

  const isTop = () => stack[stack.length - 1] === id

  function onKey(e: KeyboardEvent) {
    if (e.key !== 'Tab' || !root.value || !isTop()) return
    const all = focusables(root.value)
    if (!all.length) {
      e.preventDefault()
      return
    }
    const first = all[0]!
    const last = all[all.length - 1]!
    const active = document.activeElement
    if (e.shiftKey && (active === first || !root.value.contains(active))) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && (active === last || !root.value.contains(active))) {
      e.preventDefault()
      first.focus()
    }
  }

  onMounted(async () => {
    returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null
    stack.push(id)
    document.addEventListener('keydown', onKey)
    await nextTick()
    const el = root.value
    if (!el || el.contains(document.activeElement)) return
    const target =
      el.querySelector<HTMLElement>('[autofocus]') ??
      focusables(el).find((f) => f.matches('input, textarea, select')) ??
      focusables(el).find((f) => !f.matches('.modal-head .icon-btn, .drawer-top .icon-btn')) ??
      el
    target.focus({ preventScroll: true })
  })

  onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey)
    const at = stack.indexOf(id)
    if (at !== -1) stack.splice(at, 1)
    // the opener may be gone (a deleted card, a page we navigated away from)
    if (returnTo?.isConnected) returnTo.focus({ preventScroll: true })
  })

  return { isTop }
}