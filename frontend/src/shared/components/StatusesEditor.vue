<template>
  <p class="note">
    Статусы доски и фильтр «Статус». «Новое», «Готово» и «Отклонено» — базовые: их можно переименовать,
    но нельзя удалить и переместить. Свои статусы встают между «Новое» и «Готово» — их можно перетаскивать
    за ручку слева, чтобы менять порядок.
  </p>

  <ul class="cat-list cat-list-drag">
    <li
      v-for="s in board.statuses"
      :key="s.id"
      :class="{ draggable: !s.is_system, dragging: dragId === s.id, 'drag-over': dragOverId === s.id }"
      :draggable="!s.is_system && editingId === null"
      @dragstart="onDragStart(s, $event)"
      @dragover.prevent="onDragOver(s)"
      @dragleave="dragOverId === s.id && (dragOverId = null)"
      @drop.prevent="onDrop(s)"
      @dragend="onDragEnd"
    >
      <form v-if="editingId === s.id" class="cat-edit" @submit.prevent="saveEdit">
        <div class="cat-row">
          <InputComponent v-model="editName" maxlength="40" aria-label="Название статуса" autofocus />
          <button class="btn btn-primary" type="submit" :disabled="!editColorValid">Сохранить</button>
          <button class="btn" type="button" @click="editingId = null">Отмена</button>
        </div>
        <ColorPaletteComponent v-model="editColor" v-model:valid="editColorValid" label="Цвет статуса" variant="status" />
        <div class="cat-preview">
          <span class="label">Как будет выглядеть:</span>
          <span class="pill" :data-color="editColor ?? 'none'" :style="entityColorStyle(editColor)">{{ editName.trim() || s.name }}</span>
        </div>
        <span v-if="editError" class="err cat-err">{{ editError }}</span>
      </form>
      <div v-else class="cat-row">
        <!-- the grip is only interactive for custom columns; system ones get a dash in its place so names align -->
        <span v-if="!s.is_system" class="cat-grip" aria-label="Перетащите, чтобы изменить порядок" title="Перетащите, чтобы изменить порядок"><AppIcon name="grip" :size="16" /></span>
        <span v-else class="cat-grip cat-grip-empty" aria-hidden="true" />
        <span class="pill" :data-color="s.color ?? 'none'" :style="entityColorStyle(s.color)">{{ s.name }}</span>
        <span v-if="s.is_system" class="chip">базовый</span>
        <span class="spacer" />
        <button class="link-btn" type="button" @click="startEdit(s)">Изменить</button>
        <button v-if="!s.is_system" class="link-btn" type="button" @click="askRemove(s)">Удалить</button>
      </div>
    </li>
  </ul>

  <form class="cat-edit" @submit.prevent="add">
    <div class="cat-row">
      <InputComponent
        v-model="newName"
        maxlength="40"
        placeholder="Новый статус, например «В работе»"
        aria-label="Новый статус"
        @input="addError = ''"
      />
      <button class="btn btn-add" type="submit" :disabled="!newName.trim() || !newColorValid">Добавить</button>
    </div>
    <ColorPaletteComponent v-model="newColor" v-model:valid="newColorValid" label="Цвет нового статуса" variant="status" />
    <div v-if="newName.trim()" class="cat-preview">
      <span class="label">Как будет выглядеть:</span>
      <span class="pill" :data-color="newColor ?? 'none'" :style="entityColorStyle(newColor)">{{ newName.trim() }}</span>
    </div>
    <span v-if="addError" class="err cat-err">{{ addError }}</span>
  </form>
</template>

<script setup lang="ts">
import { entityColorStyle } from '@/shared/utils/entityColor'
import { ref } from 'vue'
import { ApiError } from '@/api/client'
import type { PaletteColor, Status } from '@/api/types'
import { useConfirmStore } from '@/store/modals/useConfirmStore'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import ColorPaletteComponent from '@/shared/ui/ColorPaletteComponent.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'

// Team settings: columns of the kanban. Три системных (new/done/rejected) защищены от
// удаления; переименование и смена цвета разрешены всем. Удаление кастомного — посты
// переезжают в «Новое». Работает с проектом, открытым в useBoardStore.
const board = useBoardStore()
const notification = useNotificationStore()
const confirm = useConfirmStore()

const newName = ref('')
const newColor = ref<PaletteColor | null>(null)
const addError = ref('')
const newColorValid = ref(true)
const editColorValid = ref(true)
const editingId = ref<number | null>(null)
const editName = ref('')
const editColor = ref<PaletteColor | null>(null)
const editError = ref('')

// drag&drop для порядка кастомных статусов; системные не двигаются
const dragId = ref<number | null>(null)
const dragOverId = ref<number | null>(null)

function onDragStart(s: Status, e: DragEvent) {
  dragId.value = s.id
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(s.id))
  }
}

function onDragOver(s: Status) {
  if (dragId.value === null || s.is_system || s.id === dragId.value) {
    dragOverId.value = null
    return
  }
  dragOverId.value = s.id
}

async function onDrop(target: Status) {
  const sourceId = dragId.value
  dragId.value = null
  dragOverId.value = null
  if (sourceId === null || target.is_system || target.id === sourceId) return

  const custom = board.statuses.filter((s) => !s.is_system)
  const from = custom.findIndex((s) => s.id === sourceId)
  const to = custom.findIndex((s) => s.id === target.id)
  if (from < 0 || to < 0) return
  const next = custom.slice()
  // from is a valid index above, so splice always returns one element
  const moved = next.splice(from, 1)[0]!
  next.splice(to, 0, moved)
  try {
    await board.reorderStatuses(next.map((s) => s.id))
  } catch (e) {
    notification.error(message(e))
  }
}

function onDragEnd() {
  dragId.value = null
  dragOverId.value = null
}

const message = (e: unknown) => (e instanceof ApiError ? (e.field('name') ?? e.field('color') ?? e.message) : String(e))

async function add() {
  addError.value = ''
  if (!newName.value.trim() || !newColorValid.value) return
  try {
    await board.addStatus(newName.value.trim(), newColor.value)
    newName.value = ''
    newColor.value = null
  } catch (e) {
    addError.value = message(e)
  }
}

function startEdit(s: Status) {
  editingId.value = s.id
  editName.value = s.name
  editColor.value = s.color
  editError.value = ''
  editColorValid.value = true
}

async function saveEdit() {
  if (editingId.value === null || !editColorValid.value) return
  try {
    await board.updateStatus(editingId.value, editName.value.trim(), editColor.value)
    editingId.value = null
  } catch (e) {
    editError.value = message(e)
  }
}

async function askRemove(s: Status) {
  const ok = await confirm.ask({
    title: 'Удалить статус?',
    message: `Удалить «${s.name}»? Карточки с этим статусом перейдут в «Новое».`,
    confirmText: 'Удалить',
    danger: true,
  })
  if (!ok) return
  try {
    await board.deleteStatus(s.id)
    notification.success('Статус удалён, карточки переехали в «Новое»')
  } catch (e) {
    notification.error(message(e))
  }
}
</script>