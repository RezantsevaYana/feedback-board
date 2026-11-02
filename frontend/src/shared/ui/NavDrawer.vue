<template>
  <Teleport to="body">
    <Transition name="nav-drawer" appear @after-leave="emit('close')">
      <div v-if="show" ref="root" class="nav-drawer-bg" @click.self="requestClose">
        <!-- autofocus: the focus trap lands on the panel itself, not on its first row (no ring on a tap) -->
        <div ref="panel" class="nav-drawer" role="dialog" aria-modal="true" :aria-label="label" tabindex="-1" autofocus>
          <slot />
        </div>
        <!-- the close button sits right over the «Меню» button that opened it: the lines fold into a cross -->
        <button ref="closeBtn" type="button" class="nav-drawer-close" :class="{ anchored }" aria-label="Закрыть меню" @click="requestClose">
          <BurgerIcon :open="crossed" />
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useEscapeKey } from '@/shared/composables/useEscapeKey'
import { useFocusTrap } from '@/shared/composables/useFocusTrap'
import BurgerIcon from '@/shared/ui/BurgerIcon.vue'

// Tablet and phone: the side menu drops down under the header as a compact panel, its height by content, the page
// dimmed below (prototype: the menu question of 02.11.2026). Closes on ×,
// the backdrop, Esc and on any navigation (a link inside was followed). Like BaseModal, the parent unmounts it on
// `close`, after the leave transition has played. `anchor` is the selector of the button that opened it: the
// close button takes its exact place and size, so the burger turns into a cross where the finger already is.
const props = defineProps<{
  label: string
  anchor?: string
}>()
const emit = defineEmits<{
  close: []
}>()

const show = ref(true)
const root = ref<HTMLElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const closeBtn = ref<HTMLButtonElement | null>(null)
const anchored = ref(false)
const crossed = ref(false)
const route = useRoute()

function requestClose() {
  crossed.value = false
  show.value = false
}
provide<() => void>('navDrawer:close', requestClose)
// the parent closes it through the transition too (BoardPage, before opening an editor from the drawer)
defineExpose({ close: requestClose })

// position comes from the page at the moment of opening, so it's set in code rather than in the template
async function placeOverAnchor() {
  await nextTick()
  const trigger = props.anchor ? document.querySelector<HTMLElement>(props.anchor) : null
  const btn = closeBtn.value
  const box = trigger?.getBoundingClientRect()
  if (btn && box && box.width > 0) {
    btn.style.top = `${box.top}px`
    btn.style.left = `${box.left}px`
    btn.style.width = `${box.width}px`
    btn.style.height = `${box.height}px`
    // the menu's first rows start under the button, not behind it
    panel.value?.style.setProperty('--drawer-top', `${box.bottom + 8}px`)
    anchored.value = true
  }
  // a frame later, so the lines visibly fold instead of starting as a cross
  requestAnimationFrame(() => (crossed.value = true))
}

const { isTop } = useFocusTrap(root)
useEscapeKey(() => isTop() && requestClose())
watch(() => route.fullPath, requestClose)
onMounted(() => {
  document.body.classList.add('locked')
  void placeOverAnchor()
})
onBeforeUnmount(() => document.body.classList.remove('locked'))
</script>