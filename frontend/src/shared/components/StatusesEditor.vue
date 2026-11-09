<template>
  <p class="note">
    Порядок статусов задаёт расположение колонок на канбан-доске. В режиме списка он не влияет
    на порядок предложений — там используется выбранная сортировка. Перетащите статус за ручку
    слева или выберите «⋯» → «Выше / Ниже».
  </p>

  <div class="status-default-field">
    <SelectComponent
      portal
      :model-value="board.project?.default_status_id ?? null"
      :options="board.statuses.map(s => ({ value: s.id, label: s.name }))"
      label="Новые предложения попадают в"
      :disabled="savingDefault"
      :aria-busy="savingDefault"
      @update:model-value="setDefault"
    />
    <p class="note">Этот статус получают новые предложения с доски и из виджета. Порядок колонок на него не влияет.</p>
  </div>

  <ul class="cat-list cat-list-drag">
    <!-- reorder by the grip with pointer events: mouse and finger alike (HTML5 drag & drop does nothing on touch) -->
    <li
      v-for="s in board.statuses"
      :key="s.id"
      :data-status-id="s.id"
      :class="{ draggable: !onlyOne, dragging: dragId === s.id, 'drag-over': dragOverId === s.id }"
    >
      <form v-if="editingId === s.id" class="cat-edit" @submit.prevent="saveEdit">
        <!-- says what this is: the field holds the name being edited («Готово» alone read like a button) -->
        <p class="cat-edit-caption">Изменить статус</p>
        <div class="cat-row editing">
          <ColorPaletteComponent v-model="editColor" v-model:valid="editColorValid" label="Цвет статуса" variant="status" />
          <InputComponent v-model="editName" maxlength="40" aria-label="Название статуса" autofocus />
          <button class="btn btn-primary" type="submit" :disabled="!editColorValid">Сохранить</button>
          <!-- phone: just «×»; the word stays for screen readers -->
          <button class="btn cat-cancel" type="button" aria-label="Отмена" @click="editingId = null"><span class="cat-cancel-word">Отмена</span><AppIcon name="close" :size="16" class="cat-cancel-icon" /></button>
        </div>
        <div class="cat-preview">
          <span class="label">Как будет выглядеть:</span>
          <span class="pill" :data-color="editColor ?? 'none'" :style="entityColorStyle(editColor)">{{ editName.trim() || s.name }}</span>
        </div>
        <span v-if="editError" class="err cat-err">{{ editError }}</span>
      </form>
      <div v-else class="cat-row">
        <button
          type="button"
          class="cat-grip"
          :disabled="onlyOne || reordering"
          :aria-label="onlyOne ? 'Для перемещения нужен ещё один статус' : 'Перетащите, чтобы изменить порядок'"
          :title="onlyOne ? 'Для перемещения нужен ещё один статус' : 'Перетащите, чтобы изменить порядок'"
          @pointerdown="startDrag(s, $event)"
        ><AppIcon name="grip" :size="16" /></button>
        <span class="pill" :data-color="s.color ?? 'none'" :style="entityColorStyle(s.color)">{{ s.name }}</span>
        <span class="spacer" />
        <button class="link-btn" type="button" @click="startEdit(s)">Изменить</button>
        <button class="link-btn" type="button" :disabled="board.statuses.length <= 1" :title="board.statuses.length <= 1 ? 'Нельзя удалить последний статус' : undefined" @click="askRemove(s)">Удалить</button>
        <!-- phone: one «⋯»; it also moves any column up / down, the keyboard's way to reorder -->
        <!-- the wrapper carries the class: MenuComponent hands its attributes to its trigger button -->
        <span class="cat-more"><MenuComponent :items="rowMenu(s)" :aria-label="`Действия: ${s.name}`" /></span>
      </div>
    </li>
  </ul>
  <p v-if="onlyOne" class="note status-limit-note">Последний статус нельзя удалить. Чтобы менять порядок, добавьте ещё один.</p>

  <form class="cat-edit cat-add" @submit.prevent="add">
    <div class="cat-row">
      <ColorPaletteComponent v-model="newColor" v-model:valid="newColorValid" :compact-trigger="settingsLayout" label="Цвет нового статуса" variant="status" />
      <InputComponent
        v-model="newName"
        maxlength="40"
        :placeholder="settingsLayout ? 'Новый статус' : 'Новый статус, например «В работе»'"
        aria-label="Новый статус"
        @input="addError = ''"
      />
      <button class="btn btn-add" type="submit" :disabled="!newName.trim() || !newColorValid" :aria-label="settingsLayout ? 'Добавить' : undefined"><span class="cat-add-word">Добавить</span><span v-if="settingsLayout" class="cat-add-icon" aria-hidden="true">+</span></button>
    </div>
    <div v-if="newName.trim()" class="cat-preview">
      <span class="label">Как будет выглядеть:</span>
      <span class="pill" :data-color="newColor ?? 'none'" :style="entityColorStyle(newColor)">{{ newName.trim() }}</span>
    </div>
    <span v-if="addError" class="err cat-err">{{ addError }}</span>
  </form>
  <StatusDeleteModal v-if="deleting" :key="deleting.id" :status="deleting" @close="deleting = null" />
</template>

<script setup lang="ts">
import { entityColorStyle } from '@/shared/utils/entityColor'
import { computed, onBeforeUnmount, ref } from 'vue'
import { ApiError } from '@/api/client'
import type { PaletteColor, Status } from '@/api/types'
import { useNotificationStore } from '@/store/modals/useNotificationStore'
import { useBoardStore } from '@/store/board/useBoardStore'
import AppIcon from '@/shared/ui/AppIcon.vue'
import ColorPaletteComponent from '@/shared/ui/ColorPaletteComponent.vue'
import InputComponent from '@/shared/ui/InputComponent.vue'
import MenuComponent, { type MenuItem } from '@/shared/ui/MenuComponent.vue'
import SelectComponent from '@/shared/ui/SelectComponent.vue'
import StatusDeleteModal from './StatusDeleteModal.vue'

// All statuses share editing, ordering and deletion; only the last one is protected.
defineProps<{ settingsLayout?: boolean }>()
const board = useBoardStore()
const notification = useNotificationStore()
const deleting = ref<Status | null>(null)
const savingDefault = ref(false)
const onlyOne = computed(() => board.statuses.length <= 1)

const newName = ref('')
const newColor = ref<PaletteColor | null>(null)
const addError = ref('')
const newColorValid = ref(true)
const editColorValid = ref(true)
const editingId = ref<number | null>(null)
const editName = ref('')
const editColor = ref<PaletteColor | null>(null)
const editError = ref('')

// All columns share the same order, with a menu alternative to pointer dragging.
const dragId = ref<number | null>(null)
const dragOverId = ref<number | null>(null)

const reordering = ref(false)

// pointer events, not HTML5 drag & drop: one path for mouse and finger. The grip has touch-action: none,
// so dragging it doesn't scroll the page; the row under the pointer gets the «drop here» line
function startDrag(s: Status, e: PointerEvent) {
  if (onlyOne.value || editingId.value !== null || reordering.value || e.button > 0) return
  e.preventDefault()
  dragId.value = s.id
  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', onPointerUp)
  window.addEventListener('pointercancel', stopDrag)
}
function onPointerMove(e: PointerEvent) {
  const row = document.elementFromPoint(e.clientX, e.clientY)?.closest<HTMLElement>('[data-status-id]')
  const id = row ? Number(row.dataset.statusId) : null
  const target = board.statuses.find((s) => s.id === id)
  dragOverId.value = target && target.id !== dragId.value ? target.id : null
}
function onPointerUp() {
  const from = dragId.value
  const to = dragOverId.value
  stopDrag()
  if (from !== null && to !== null) void moveTo(from, to)
}
function stopDrag() {
  dragId.value = null
  dragOverId.value = null
  window.removeEventListener('pointermove', onPointerMove)
  window.removeEventListener('pointerup', onPointerUp)
  window.removeEventListener('pointercancel', stopDrag)
}
onBeforeUnmount(stopDrag)

// Move any column, including those created with the board.
async function moveTo(sourceId: number, targetId: number) {
  const statuses = board.statuses
  const from = statuses.findIndex((s) => s.id === sourceId)
  const to = statuses.findIndex((s) => s.id === targetId)
  if (from < 0 || to < 0 || from === to) return
  if (reordering.value) return
  const next = statuses.slice()
  // from is a valid index above, so splice always returns one element
  const moved = next.splice(from, 1)[0]!
  next.splice(to, 0, moved)
  reordering.value = true
  try {
    await board.reorderStatuses(next.map((s) => s.id))
  } catch (e) {
    notification.error(message(e))
  } finally {
    reordering.value = false
  }
}

function rowMenu(s: Status): MenuItem[] {
  const statuses = board.statuses
  const index = statuses.findIndex((c) => c.id === s.id)
  return [
    { label: 'Изменить', run: () => startEdit(s) },
    { label: 'Выше', run: () => moveTo(s.id, statuses[index - 1]!.id), disabled: reordering.value || index <= 0, separated: true },
    { label: 'Ниже', run: () => moveTo(s.id, statuses[index + 1]!.id), disabled: reordering.value || index >= statuses.length - 1 },
    { label: 'Удалить', run: () => askRemove(s), disabled: board.statuses.length <= 1, danger: true, separated: true },
  ]
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

function askRemove(s: Status) {
  if (board.statuses.length > 1) deleting.value = s
}

async function setDefault(id: number | null) {
  if (id === null || savingDefault.value) return
  savingDefault.value = true
  try {
    await board.setDefaultStatus(id)
  } catch (e) {
    notification.error(message(e))
  } finally {
    savingDefault.value = false
  }
}
</script>

<style scoped lang="scss">
.status-default-field { display: grid; gap: 6px; }
.status-default-field > .note { margin: 0; font-size: 12px; }
.status-limit-note { margin: 0; font-size: 12px; }
.cat-grip { border: 0; background: none; }
.cat-grip:disabled { opacity: 0.3; cursor: not-allowed; touch-action: auto; }
.cat-row > .link-btn:disabled {
  color: var(--muted);
  opacity: 0.4;
  cursor: not-allowed;
  text-decoration: none;
}
</style>
