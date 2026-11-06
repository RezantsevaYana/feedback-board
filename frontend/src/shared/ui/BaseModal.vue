<template>
  <Teleport to="body">
    <Transition name="modal" appear @after-leave="emit('close')">
      <div
        v-if="show"
        class="modal-bg"
        @mousedown="onBgMouseDown"
        @mouseup.self="onBgMouseUp"
      >
        <component
          :is="props.tag ?? 'div'"
          ref="modalEl"
          class="modal"
          :class="{ narrow, dragging, dismissing: swipeOffset > 0 }"
          :style="swipeStyle"
          role="dialog"
          aria-modal="true"
          tabindex="-1"
          :aria-label="label"
          v-bind="$attrs"
          @touchstart.passive="onTouchStart"
          @touchmove="onTouchMove"
          @touchend="onTouchEnd"
          @touchcancel="onTouchEnd"
        >
          <span class="modal-grip" aria-hidden="true" />
          <slot />
        </component>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref } from 'vue'
import { useEscapeKey } from '@/shared/composables/useEscapeKey'
import { useFocusTrap } from '@/shared/composables/useFocusTrap'

// Закрывается на крестик (slot), клик по фону, Esc, и свайп вниз на мобиле (≤479); `persistent` — только своими кнопками.
// Анимация симметричная: открытие и закрытие проходят Transition. Чтобы leave успел
// отыграть, внутренние компоненты (×-кнопки, «Отмена», успех после save) закрывают
// модалку через inject('baseModal:close'), а не прямым emit('close') наверх —
// иначе родитель мгновенно размонтирует BaseModal.
defineOptions({ inheritAttrs: false })
const props = defineProps<{
  label: string
  narrow?: boolean
  tag?: string
  /** no way out but the modal's own buttons: background click, Esc and the swipe do nothing */
  persistent?: boolean
}>()
const emit = defineEmits<{
  close: []
}>()

const show = ref(true)
const modalEl = ref<HTMLElement | null>(null)

function requestClose() {
  show.value = false
}

// Закрытие по клику на фон — только если и mousedown, и mouseup
// случились на самом фоне. Иначе выделение текста внутри модалки,
// отпущенное за её пределами, закрывает окно.
let bgPressed = false
function onBgMouseDown(e: MouseEvent) {
  bgPressed = e.target === e.currentTarget
}
function onBgMouseUp() {
  if (bgPressed && !props.persistent) requestClose()
  bgPressed = false
}

// отдаём внутренним компонентам, чтобы они закрывали модалку через анимацию
provide<() => void>('baseModal:close', requestClose)

// keyboard focus moves into the dialog, stays there on Tab and returns to the opener on close
const { isTop } = useFocusTrap(modalEl)

// пока галерея скриншотов открыта (lightgallery), Esc берёт она; из двух модалок (подтверждение
// поверх категорий) закрывается только верхняя
useEscapeKey(() => !props.persistent && isTop() && !document.querySelector('.lg-outer.lg-visible') && requestClose())
onMounted(() => document.body.classList.add('locked'))
onBeforeUnmount(() => document.body.classList.remove('locked'))

// --- Свайп вниз на мобильном bottom-sheet -----------------------------------
const DISMISS_THRESHOLD = 110 // px свайпа — закрываем
const dragging = ref(false)
const swipeOffset = ref(0)
let startY = 0
let lastY = 0
let lastT = 0
let velocity = 0

function isBottomSheet() {
  return window.matchMedia('(max-width: 479px)').matches
}
function onTouchStart(e: TouchEvent) {
  if (props.persistent || !isBottomSheet() || !modalEl.value) return
  // не перехватываем жесты внутри прокручиваемого контента, пока он не прокручен в самый верх
  if (modalEl.value.scrollTop > 0) return
  const t = e.touches[0]
  if (!t) return
  startY = lastY = t.clientY
  lastT = e.timeStamp
  dragging.value = true
  velocity = 0
}
function onTouchMove(e: TouchEvent) {
  if (!dragging.value) return
  const t = e.touches[0]
  if (!t) return
  const dy = t.clientY - startY
  if (dy < 0) {
    swipeOffset.value = 0
    return
  }
  // блокируем нативный скролл только пока тянем вниз от самого верха
  if (e.cancelable) e.preventDefault()
  swipeOffset.value = dy
  const dt = e.timeStamp - lastT || 1
  velocity = (t.clientY - lastY) / dt
  lastY = t.clientY
  lastT = e.timeStamp
}
function onTouchEnd() {
  if (!dragging.value) return
  const shouldClose = swipeOffset.value > DISMISS_THRESHOLD || velocity > 0.6
  dragging.value = false
  if (shouldClose) {
    // отпустим пружиной: сначала доведём до низа через transition, потом закроем
    swipeOffset.value = window.innerHeight
    setTimeout(requestClose, 160)
  } else {
    swipeOffset.value = 0
  }
}
const swipeStyle = computed(() => {
  if (!swipeOffset.value) return null
  return {
    transform: `translateY(${swipeOffset.value}px)`,
    transition: dragging.value ? 'none' : 'transform 0.18s ease-out',
  }
})
</script>
