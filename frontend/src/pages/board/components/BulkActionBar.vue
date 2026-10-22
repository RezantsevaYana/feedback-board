<template>
  <StatusReplyModal
    v-if="asking"
    :posts="asking.posts"
    :status="asking.status"
    @close="asking = null"
  />
  <Transition name="bulk-bar">
    <div
      v-if="board.selectionMode"
      ref="root"
      class="bulk-bar"
      :class="{ empty: !board.hasSelection }"
      role="region"
      aria-label="Массовые действия над выбранными карточками"
    >
      <template v-if="board.hasSelection">
        <span class="bulk-count">Выбрано: {{ board.selectionCount }}</span>

        <div class="bulk-group" :class="{ open: openMenu === 'status' }">
          <button type="button" class="btn bulk-trigger" aria-haspopup="menu" :aria-expanded="openMenu === 'status'" @click="toggle('status')">
            В столбец <AppIcon name="chevron" />
          </button>
          <ul v-if="openMenu === 'status'" class="bulk-menu" role="menu">
            <li v-for="s in board.statuses" :key="s.id" role="none">
              <button type="button" role="menuitem" class="bulk-item" @click="pickStatus(s)">
                <span class="pill" :data-color="s.color ?? 'none'">{{ s.name }}</span>
              </button>
            </li>
          </ul>
        </div>

        <div class="bulk-group" :class="{ open: openMenu === 'category' }">
          <button type="button" class="btn bulk-trigger" aria-haspopup="menu" :aria-expanded="openMenu === 'category'" @click="toggle('category')">
            Категория <AppIcon name="chevron" />
          </button>
          <ul v-if="openMenu === 'category'" class="bulk-menu" role="menu">
            <li v-for="c in board.categories" :key="c.id" role="none">
              <button type="button" role="menuitem" class="bulk-item" @click="pickCategory(c.id)">
                <span class="type slant" :data-color="c.color ?? 'none'">{{ c.name }}</span>
              </button>
            </li>
            <li role="none">
              <button type="button" role="menuitem" class="bulk-item bulk-item-plain" @click="pickCategory(null)">
                Без категории
              </button>
            </li>
          </ul>
        </div>

        <button type="button" class="btn" @click="toggleHide">
          {{ board.allSelectedHidden ? 'Показать' : 'Скрыть' }}
        </button>
        <button type="button" class="btn danger" @click="askDelete">Удалить</button>
      </template>
      <template v-else>
        <span class="bulk-empty">
          Отметьте карточки чекбоксом — Shift+клик выделяет диапазон.
        </span>
      </template>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { errorMessage } from '@/api/client'
import type { Category, Post, Status, SystemStatusSlug } from '@/api/types'
import { STATUS_REPLY } from '@/shared/constants/dict'
import { useBoardStore } from '@/store/board/useBoardStore'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useEscapeKey } from '@/shared/composables/useEscapeKey'
import AppIcon from '@/shared/ui/AppIcon.vue'
import StatusReplyModal from './StatusReplyModal.vue'

// Floating action bar for mass triage from the list view. Appears when at least one card
// is selected; a system «Готово» / «Отклонено» opens the shared-reply modal, everything else
// fires immediately. Clicks outside the bar close any open sub-menu; Esc clears the whole selection.
const board = useBoardStore()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const root = ref<HTMLElement | null>(null)
const openMenu = ref<'status' | 'category' | null>(null)
const asking = ref<{ posts: Post[]; status: Status } | null>(null)

function toggle(name: 'status' | 'category') {
  openMenu.value = openMenu.value === name ? null : name
}

async function pickStatus(status: Status) {
  openMenu.value = null
  if (STATUS_REPLY[status.slug as SystemStatusSlug]) {
    asking.value = { posts: [...board.selectedPosts], status }
    return
  }
  try {
    await board.bulkMove(status.id)
  } catch (e) {
    notification.error(errorMessage(e))
  }
}

async function pickCategory(categoryId: Category['id'] | null) {
  openMenu.value = null
  try {
    await board.bulkCategorize(categoryId)
  } catch (e) {
    notification.error(errorMessage(e))
  }
}

async function toggleHide() {
  openMenu.value = null
  try {
    await board.bulkHide(!board.allSelectedHidden)
  } catch (e) {
    notification.error(errorMessage(e))
  }
}

async function askDelete() {
  openMenu.value = null
  const n = board.selectionCount
  const ok = await confirm.ask({
    title: n === 1 ? 'Удалить карточку?' : `Удалить ${n} карточек?`,
    message: 'Это действие нельзя отменить. Голоса и вложения тоже уйдут.',
    confirmText: 'Удалить',
    danger: true,
  })
  if (!ok) return
  try {
    await board.bulkDelete()
  } catch (e) {
    notification.error(errorMessage(e))
  }
}

function onOutsidePointer(e: PointerEvent) {
  if (openMenu.value && !root.value?.contains(e.target as Node)) openMenu.value = null
}
watch(openMenu, (v) => {
  if (v) document.addEventListener('pointerdown', onOutsidePointer, true)
  else document.removeEventListener('pointerdown', onOutsidePointer, true)
})

useEscapeKey(() => {
  if (openMenu.value) openMenu.value = null
  else if (board.hasSelection) board.clearSelection()
  else board.exitSelection()
})

onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutsidePointer, true))
</script>
