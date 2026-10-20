import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface ConfirmOptions {
  /** Заголовок модалки. По умолчанию — «Подтверждение». */
  title?: string
  /** Основной текст — что именно подтверждаем. Называйте объект явно. */
  message: string
  /** Подпись «подтвердить». По умолчанию — «Продолжить». */
  confirmText?: string
  /** Подпись «отмена». По умолчанию — «Отмена». */
  cancelText?: string
  /** Красная кнопка подтверждения, для разрушительных действий (удаление). */
  danger?: boolean
}

// Универсальная модалка подтверждения. Рендерится один раз в App.vue (<ConfirmModal/>).
// Из любого места: `const ok = await useConfirmStore().ask({ message: '…' })` —
// true = пользователь нажал подтвердить, false = отменил.
export const useConfirmStore = defineStore('confirm', () => {
  const open = ref(false)
  const options = ref<ConfirmOptions | null>(null)
  let resolver: ((v: boolean) => void) | null = null

  function ask(opts: ConfirmOptions): Promise<boolean> {
    // если открыта предыдущая — закрываем её с «отменой»
    resolver?.(false)
    options.value = opts
    open.value = true
    return new Promise((resolve) => {
      resolver = resolve
    })
  }

  function resolve(answer: boolean) {
    resolver?.(answer)
    resolver = null
    open.value = false
  }

  return { open, options, ask, resolve }
})
