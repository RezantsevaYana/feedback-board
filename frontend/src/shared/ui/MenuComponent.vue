<template>
  <div ref="root" class="menu" :class="{ open }">
    <button
      ref="trigger"
      type="button"
      class="icon-btn menu-trigger"
      aria-haspopup="menu"
      :aria-expanded="open"
      :aria-controls="`${uid}-menu`"
      v-bind="$attrs"
      @click.stop="toggle"
      @keydown.down.prevent="openAt(0)"
      @keydown.up.prevent="openAt(items.length - 1)"
    >
      <slot><AppIcon name="more" /></slot>
    </button>
    <div v-if="open" :id="`${uid}-menu`" ref="list" class="menu-list" :class="{ left: align === 'left' }" role="menu" @keydown="onKey">
      <template v-for="(item, i) in items" :key="item.label">
        <span v-if="item.separated && i > 0" class="menu-sep" role="separator" />
        <button
          type="button"
          class="menu-item"
          :class="{ danger: item.danger }"
          role="menuitem"
          tabindex="-1"
          :disabled="item.disabled"
          @click.stop="pick(item)"
        >
          {{ item.label }}<kbd v-if="item.key" class="menu-key">{{ item.key }}</kbd>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useId } from 'vue'
import AppIcon from '@/shared/ui/AppIcon.vue'

export interface MenuItem {
  label: string
  run: () => unknown
  /** shortcut shown on the right, e.g. «2» */
  key?: string
  danger?: boolean
  disabled?: boolean
  /** a thin gap above the item starts a new group */
  separated?: boolean
}

// Action menu behind an icon button (kebab by default): arrows move, Enter picks, Esc / click outside close.
defineOptions({ inheritAttrs: false })
defineProps<{
  items: MenuItem[]
  align?: 'left' | 'right'
}>()

const uid = useId()
const open = ref(false)
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const list = ref<HTMLElement | null>(null)

function buttons() {
  return Array.from(list.value?.querySelectorAll<HTMLButtonElement>('.menu-item:not(:disabled)') ?? [])
}
function focusAt(i: number) {
  const all = buttons()
  if (all.length) all[(i + all.length) % all.length]!.focus()
}
async function openAt(i: number) {
  open.value = true
  document.addEventListener('pointerdown', onOutside, true)
  await nextTick()
  focusAt(i)
}
function close(refocus = true) {
  open.value = false
  document.removeEventListener('pointerdown', onOutside, true)
  if (refocus) trigger.value?.focus()
}
function toggle() {
  if (open.value) close()
  else openAt(0)
}
function onOutside(e: PointerEvent) {
  if (!root.value?.contains(e.target as Node)) close(false)
}
function onKey(e: KeyboardEvent) {
  const all = buttons()
  const i = all.indexOf(document.activeElement as HTMLButtonElement)
  if (e.key === 'ArrowDown') focusAt(i + 1)
  else if (e.key === 'ArrowUp') focusAt(i - 1)
  else if (e.key === 'Home') focusAt(0)
  else if (e.key === 'End') focusAt(all.length - 1)
  else if (e.key === 'Escape' || e.key === 'Tab') close(e.key === 'Escape')
  else return
  e.preventDefault()
  e.stopPropagation()
}
function pick(item: MenuItem) {
  close()
  item.run()
}

onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside, true))
</script>